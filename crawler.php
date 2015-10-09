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


$startPage = 'http://rabota.ua/';
//$startPage = 'https://www.google.com.ua';
//$startPage = 'localhost:3030';
//$startPage = 'www.apple.com';
//$startPage = 'hh.ua';
//$startPage = 'habrahabr.ru';

libxml_use_internal_errors(true);

$doc = new DOMDocument();
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
function getImgTagCount(DOMDocument $doc) {
    $images = $doc->getElementsByTagName('img');
    return $images->length;
}

function getLinksOf(DOMDocument $doc) {
    return $doc->getElementsByTagName('a');
}

function getHrefOf(DOMNode $node) {
    if (is_object($node->attributes->getNamedItem('href'))) {
        return $node->attributes->getNamedItem('href')->nodeValue;
    }
    return '';
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

function crawlPageLinks($url, DOMDocument $doc, CurlWrapper $curl, ParsedPagesCache $parsedPages) {

    global $startPage;

    if ($parsedPages->isUrlInCache($url)) {
        return;
    }

    echo "Parsing: $url ...\n";

    $html = $curl->get($url);

    if ($html) {
        $doc->loadHTML($html, LIBXML_COMPACT);

        $imageCount = getImgTagCount($doc);
        $parsedPages->add(new Record($url, $imageCount, 0));
        echo $url . ': ' . $imageCount . PHP_EOL;

        $links = getLinksOf($doc);
        foreach ($links as $link) {
            $url = getHrefOf($link);
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

