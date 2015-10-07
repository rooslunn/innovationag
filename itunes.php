<?php
/**
 * Created by PhpStorm.
 * User: russ
 * Date: 10/7/15
 * Time: 4:02 PM
 */


function getImgTagCount(DOMDocument $doc) {
    $images = $doc->getElementsByTagName('img');
    return $images->length;
}

function crawlPageLinks($url, DOMDocument $doc, $ch, array $parsed) {

    global $start_page;

    echo "Parsing: $url ...\n";

    curl_setopt($ch, CURLOPT_URL, $url);
    $html = curl_exec($ch);

    if ($html) {
        $doc->loadHTML($html, LIBXML_COMPACT);

        $imageCount = getImgTagCount($doc);
        echo $url . '; ' . $imageCount . PHP_EOL;
        $parsed[$url] = $imageCount;

        $links = $doc->getElementsByTagName('a');
        foreach ($links as $link) {
            $url = $link->attributes->getNamedItem('href')->nodeValue;
            if (!array_key_exists($url, $parsed) && isRelativeLink($url)) {
                $url = $start_page . $url;
                crawlPageLinks($url, $doc, $ch, $parsed);
            }
        }
    }
}

function isRelativeLink($url) {
    return preg_match('/(^[^(http|https|ftp|www|#)]\/?\w*)/', $url);
}

//$url = 'https://itunes.apple.com/us/app/candy-crush-saga/id553834731';
//$start_page = 'http://rabota.ua/';
//$start_page = 'https://www.google.com.ua';
//$start_page = 'http://localhost:3030/';
$start_page = 'www.apple.com';

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

libxml_use_internal_errors(true);

$doc = new DOMDocument();
$parsed_urls = [];

crawlPageLinks($start_page, $doc, $ch, $parsed_urls);

curl_close($ch);