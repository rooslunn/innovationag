<?php
/**
 * Created by PhpStorm.
 * User: russ
 * Date: 10/7/15
 * Time: 4:02 PM
 */

include_once 'Helpers.php';

use RKInnovationTest\ParsedPagesCache;
use RKInnovationTest\Record;
use RKInnovationTest\CurlWrapper;
use RKInnovationTest\HtmlPage;


$startPage = 'http://rabota.ua/';
//$startPage = 'https://www.google.com.ua';
//$startPage = 'localhost:3030';
//$startPage = 'www.apple.com';
//$startPage = 'hh.ua';
//$startPage = 'habrahabr.ru';

$doc = new HtmlPage();
$parsedPages = new ParsedPagesCache();
$curl = new CurlWrapper();

// Strategy

$realStartPage = $curl->getEffectiveUrl($startPage);
if ($realStartPage) {
    crawlPageLinks($realStartPage, $doc, $curl, $parsedPages);
} else {
    die('Address is unreachable');
}

/**
 * @param DOMDocument $doc
 * @return int
 */



function isSiteLink($url) {
    return preg_match('/(^[^(http|https|ftp|www|#)]\/?[%\w\p{L}]+)/iu', $url);
}

function extractLocalPath($url) {
    $matches = [];
    if (preg_match('/^\/?([%\p{L}\w\.\-\/]+)/ui', $url, $matches) === 1) {
        return $matches[1];
    }
    return false;
}

function crawlPageLinks($url, HtmlPage $doc, CurlWrapper $curl, ParsedPagesCache $parsedPages) {

    global $startPage;

    if ($parsedPages->isUrlInCache($url)) {
        return;
    }

    echo "Parsing: $url ...\n";

    $html = $curl->get($url);

    if ($html) {
        $doc->load($html);

        $imageCount = $doc->getImgTagCount();
        $parsedPages->add(new Record($url, $imageCount, 0));
        echo $url . ': ' . $imageCount . PHP_EOL;

        $links = $doc->getLinks();
        foreach ($links as $link) {
            $url = $doc->getHrefOfLink($link);
            if (isSiteLink($url)) {
                $localPath = extractLocalPath($url);
                $url = $startPage . $localPath;
                crawlPageLinks($url, $doc, $curl, $parsedPages);
            }
        }
    } else {
        echo "Cant get page $url";
    }
}

