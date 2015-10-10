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


//$startPage = 'http://rabota.ua/';
//$startPage = 'https://www.google.com.ua';
$startPage = 'localhost:3030';
//$startPage = 'www.apple.com';
//$startPage = 'hh.ua';
//$startPage = 'habrahabr.ru';

$doc = new HtmlPage();
$parsedPages = new ParsedPagesCache();
$curl = new CurlWrapper();

// Strategy

$realStartPage = $curl->getEffectiveUrl($startPage);
if ($realStartPage) {
    crawlSite($realStartPage, $doc, $curl, $parsedPages);
} else {
    die('Address is unreachable');
}


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

function crawlSite($startPage, HtmlPage $doc, CurlWrapper $curl, ParsedPagesCache $parsedPages) {
    $linkStack = [];

    $url = $startPage;

    $linkStack[] = $url;

    while (count($linkStack) > 0) {

        $url = array_shift($linkStack);

        if ($parsedPages->isUrlInCache($url)) {
            continue;
        }

        echo 'Parsing: ' . urldecode($url) . PHP_EOL;
        $html = $curl->get($url);
        if ($html === false) {
            echo 'Failed: ' . $curl->error() . PHP_EOL;
            continue;
        }

        $startTime = microtime(true);
        $doc->load($html);
        $imageCount = $doc->getImgTagCount();
        $parseTime = microtime(true) - $startTime;
        $parsedPages->add(new Record($url, $imageCount, $parseTime));
        echo $url . ': ' . $imageCount . '; ' . $parseTime . PHP_EOL;

        $links = $doc->getLinks();
        foreach ($links as $link) {
            $href = $doc->getHrefOfLink($link);
            if (isSiteLink($href)) {
                $url = $startPage . extractLocalPath($href);
                $linkStack[] =  $url;
            }
        }
    }
}

