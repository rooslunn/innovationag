<?php
/**
 * Created by PhpStorm.
 * User: russ
 * Date: 10/9/15
 * Time: 8:58 AM
 */

namespace RKInnovationTest;

/**
 * Class Record
 *
 * @package RKInnovationTest
 */
class Record {

    protected $_url;
    protected $_imageCount;
    protected $_parsingTime;

    public function __construct($url, $imageCount, $parsingTime) {
        $this->_url = $url;
        $this->_imageCount = $imageCount;
        $this->_parsingTime = $parsingTime;
    }

    public function getUrl() {
        return $this->_url;
    }

    public function getImageCount() {
        return $this->_imageCount;
    }

    public function getParsingTime() {
        return $this->_parsingTime;
    }
}

/**
 * Class CurlWrapper
 *
  * @package RKInnovationTest
 */
class CurlWrapper {

    /**
     * Send GET request to $url and returns result
     *
     * @param string $url
     * @return string
     */
    static public function get($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        unset($ch);
        return $data;
    }

    /**
     * Returns full domain name or false if unreachable
     *
     * @param string $url
     * @return bool|string
     */
    static public function getEffectiveUrl($url) {
        $result = false;
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        if ($data !== false) {
            $result = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        }
        curl_close($ch);
        unset($ch);
        return $result;
    }

}

/**
 * Class ParsedPagesList
 *
 * List for store already parsed links
 *
 * @package RKInnovationTest
 */
class ParsedPagesList {

    protected $_urls;

    public function __construct() {
        $this->_urls = [];
    }

    /**
     * Add new link to list
     *
     * @param Record $record
     */
    public function add(Record $record) {
        $this->_urls[$record->getUrl()] = $record;
    }

    /**
     * Checks if url already processed
     *
     * @param string $url
     * @return bool
     */
    public function isUrlInList($url) {
        return array_key_exists($url, $this->_urls);
    }

    /**
     * Returns list of all Records
     *
     * @return array
     */
    public function getAll() {
        return $this->_urls;
    }

    /**
     * Sorts list by image count desc
     *
     */
    public function sortByImageCount() {
        uasort($this->_urls, function(Record $v1, Record $v2) {
            $imgCount1 = $v1->getImageCount();
            $imgCount2 = $v2->getImageCount();
            if ($imgCount1 === $imgCount2) {
                return 0;
            }
            return ($imgCount1 > $imgCount2) ? -1 : 1;
        });
    }

    /**
     * Prints (echoes) list to STDOUT (for debugging)
     *
     */
    public function printAll() {
        foreach ($this->_urls as $record) {
            $url = $record->getUrl();
            $imgCnt = $record->getImageCount();
            $time = $record->getParsingTime();
            echo "$url; $imgCnt; $time\n";
        }
    }
}

class HtmlPage {

    protected $_domDoc;

    public function __construct() {
        $this->_domDoc = new \DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function load($html) {
        if ($html) {
            $this->_domDoc->loadHTML($html, LIBXML_COMPACT);
        }
    }

    public function getImgTagCount() {
        $images = $this->_domDoc->getElementsByTagName('img');
        return $images->length;
    }

    public function getLinks() {
        return $this->_domDoc->getElementsByTagName('a');
    }

    public function getHrefOfLink(\DOMNode $link) {
        if (is_object($link->attributes->getNamedItem('href'))) {
            return $link->attributes->getNamedItem('href')->nodeValue;
        }
        return '/';
    }

}

class HtmlReport {

    const TEMPLATE = 'template.html';

    protected $_pagesList;

    public function __construct(ParsedPagesList $pagesList) {
        $this->_pagesList = $pagesList;
    }

    protected function generateFileName() {
        return sprintf('report_%s.html', date('d.m.Y'));
    }

    protected function renderTable() {
        $htmlTable = '';
        foreach ($this->_pagesList->getAll() as $record) {
            $htmlTable .= sprintf("<tr><td>%s</td><td>%s</td><td>%s</td></tr>\n",
                $record->getUrl(), $record->getImageCount(), $record->getParsingTime());
        }
        return $htmlTable;
    }

    protected function readTemplateAsString() {
        $fh = fopen(self::TEMPLATE, 'r');
        if ($fh === false) {
            throw new \RuntimeException("Can't open report template file " . self::TEMPLATE);
        }
        $templateString = fread($fh, filesize(self::TEMPLATE));
        fclose($fh);
        return $templateString;
    }

    protected function save($filename, $content) {
        $fh = fopen($filename, 'w');
        fwrite($fh, $content);
        fclose($fh);
    }

    public function create() {
        $reportFilename = $this->generateFileName();
        $htmlTable = $this->renderTable();
        $content = str_replace('{{ content }}', $htmlTable, $this->readTemplateAsString());
        $this->save($reportFilename, $content);
    }

}

class UrlTools {

    static public function isLocalLink($url) {
        return preg_match('/(^[^(http|https|ftp|www)]\/?[%#\w\p{L}]+)/iu', $url);
    }

    static public function extractLocalPath($url) {
        $matches = [];
        if (preg_match('/^\/?([%\p{L}\w\.\-\/]+)/ui', $url, $matches) === 1) {
            return $matches[1];
        }
        return false;
    }

    /**
     * @param string $startPage
     * @param string $href
     * @return bool
     */
    static public function isDomainLink($startPage, $href)
    {
        return strpos(strtolower($href), strtolower($startPage)) !== false;
    }
}

interface CrawlingCommand {
    public function crawl($startPage, HtmlPage $doc, ParsedPagesList $parsedPages);
}

class ImgTagCountCommand implements CrawlingCommand {

    private function convertTimeToMiliseconds($time) {
        return floor($time * 1000);
    }

    public function crawl($startPage, HtmlPage $doc, ParsedPagesList $parsedPages) {
        $linkStack[] = $startPage;

        while (count($linkStack) > 0) {
            $url = array_shift($linkStack);

            if ($parsedPages->isUrlInList($url)) {
                continue;
            }

            echo sprintf('==> Crawling %s ...', urldecode($url));
            $startTime = microtime(true);
            $html = CurlWrapper::get($url);
            if ($html === false) {
                echo 'Failed due to curl error' . PHP_EOL;
                continue;
            }

            $doc->load($html);
            $imageCount = $doc->getImgTagCount();
            $parseTime = $this->convertTimeToMiliseconds(microtime(true) - $startTime);
            $parsedPages->add(new Record($url, $imageCount, $parseTime));

            echo sprintf("Done (%d)\n", memory_get_usage());

            $links = $doc->getLinks();
            foreach ($links as $link) {
                $href = $doc->getHrefOfLink($link);
                if (UrlTools::isLocalLink($href)) {
                    $href = $startPage . UrlTools::extractLocalPath($href);
                }
                if (UrlTools::isDomainLink($startPage, $href)) {
                    $linkStack[] =  $href;
                }
            }
        }

    }

}

/**
 * Class CrawlerApp
 *
 * Incapsulates functionality for whole app
 *
 * @package RKInnovationTest
 */
class CrawlerApp {

    protected $_htmlPage;
    protected $_parsedPages;
    protected $_startPage;
    protected $_command;

    const START_MESSAGE = 'Start crawling %s (%d)';
    const SAVE_REPORT_MESSAGE = 'Saving report...';
    const DONE_MESSAGE = 'Done';

    const COMMAND_NOT_SET_ERROR = 'Crawling Command for App is not set';
    const ADDRESS_UNREACHABLE_ERROR = 'Address is unreachable';

    public function __construct($startPage) {
        $this->_htmlPage = new HtmlPage();
        $this->_parsedPages = new ParsedPagesList();
        $this->_startPage = $startPage;
    }

    /**
     * Sets the command that process document and extracts data
     *
     * @param CrawlingCommand $command
     */
    public function setCommand(CrawlingCommand $command) {
        $this->_command = $command;
    }

    public function run() {
        if (null === $this->_command) {
            throw new \RuntimeException(self::COMMAND_NOT_SET_ERROR);
        }

        $realStartPage = CurlWrapper::getEffectiveUrl($this->_startPage);
        if ($realStartPage) {
            echo sprintf(self::START_MESSAGE . PHP_EOL, $realStartPage, memory_get_usage());
            $this->_command->crawl($realStartPage, $this->_htmlPage, $this->_parsedPages);
            $this->_parsedPages->sortByImageCount();
            $report = new HtmlReport($this->_parsedPages);
            echo self::SAVE_REPORT_MESSAGE;
            $report->create();
            echo self::DONE_MESSAGE . PHP_EOL;
        } else {
            throw new \HttpException(self::ADDRESS_UNREACHABLE_ERROR);
        }

    }
}
