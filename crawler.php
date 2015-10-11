<?php
/**
 * Created by PhpStorm.
 * User: russ
 * Date: 10/7/15
 * Time: 4:02 PM
 */

include_once 'Helpers.php';

use RKInnovationTest\ParsedPagesList;
use RKInnovationTest\Record;
use RKInnovationTest\CurlWrapper;
use RKInnovationTest\HtmlPage;
use RKInnovationTest\UrlTools;
use RKInnovationTest\HtmlReport;

$startPage = 'www.apple.com';
$startPage = 'localhost:3030';

$doc = new HtmlPage();
$parsedPages = new ParsedPagesList();

// Strategy

$realStartPage = CurlWrapper::getEffectiveUrl($startPage);
if ($realStartPage) {
    echo "Start crawling $realStartPage\n";
    crawlSite($realStartPage, $doc, $parsedPages);
    $parsedPages->sortByImageCount();
    $report = new HtmlReport($parsedPages);
    echo 'Saving report...';
    $report->create();
    echo "Done\n";
} else {
    die('Address is unreachable');
}

function crawlSite($startPage, HtmlPage $doc, ParsedPagesList $parsedPages) {

    $linkStack = [];

    $url = $startPage;

    $linkStack[] = $url;

    while (count($linkStack) > 0) {

        $url = array_shift($linkStack);

        if ($parsedPages->isUrlInCache($url)) {
            continue;
        }

        echo "==> Crawling $url...";

        $startTime = microtime(true);
        $html = CurlWrapper::get($url);
        if ($html === false) {
            echo 'Failed due to curl error' . PHP_EOL;
            continue;
        }

        $doc->load($html);
        $imageCount = $doc->getImgTagCount();
        $parseTime = microtime(true) - $startTime;
        $parsedPages->add(new Record($url, $imageCount, $parseTime));

        echo "Done\n";

        $links = $doc->getLinks();
        foreach ($links as $link) {
            $href = $doc->getHrefOfLink($link);
            if (UrlTools::isSiteLink($href)) {
                $url = $startPage . UrlTools::extractLocalPath($href);
                $linkStack[] =  $url;
            }
        }
    }
}

