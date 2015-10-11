#!/usr/bin/env php
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

const USAGE_INFO = "    Usage: php crawler.php <address>\n";

if (PHP_SAPI !== 'cli') {
    echo "App must be run from command line\n";
    die(USAGE_INFO);
}

if (count($argv) < 2) {
    die(USAGE_INFO);
}

$startPage = $argv[1];

$doc = new HtmlPage();
$parsedPages = new ParsedPagesList();

// Strategy

$realStartPage = CurlWrapper::getEffectiveUrl($startPage);
if ($realStartPage) {
    echo sprintf("Start crawling %s (%d)\n", $realStartPage, memory_get_usage());
    crawlSite($realStartPage, $doc, $parsedPages);
    $parsedPages->sortByImageCount();
    $report = new HtmlReport($parsedPages);
    echo 'Saving report...';
    $report->create();
    echo "Done\n";
} else {
    die("Address is unreachable\n");
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

        echo sprintf("Done (%d)\n", memory_get_usage());

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

