<?php
/**
 * Created by PhpStorm.
 * User: russ
 * Date: 10/9/15
 * Time: 8:58 AM
 */

namespace RKInnovationTest;

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

class CurlWrapper {

    static public function get($url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $data = curl_exec($ch);
        curl_close($ch);
        unset($ch);
        return $data;
    }

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

class ParsedPagesList {

    protected $_urls;

    public function __construct() {
        $this->_urls = [];
    }

    public function add(Record $record) {
        $this->_urls[$record->getUrl()] = $record;
    }

    public function isUrlInCache($url) {
        return array_key_exists($url, $this->_urls);
    }

    public function getAll() {
        return $this->_urls;
    }

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

    static public function isSiteLink($url) {
        return preg_match('/(^[^(http|https|ftp|www|#)]\/?[%\w\p{L}]+)/iu', $url);
    }

    static public function extractLocalPath($url) {
        $matches = [];
        if (preg_match('/^\/?([%\p{L}\w\.\-\/]+)/ui', $url, $matches) === 1) {
            return $matches[1];
        }
        return false;
    }
}
