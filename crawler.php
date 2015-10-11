#!/usr/bin/env php
<?php
/**
 * Created by PhpStorm.
 * User: russ
 * Date: 10/7/15
 * Time: 4:02 PM
 */

include_once 'Helpers.php';

use RKInnovationTest\CrawlerApp;
use RKInnovationTest\ImgTagCountCommand;

const USAGE_INFO = "    Usage: php crawler.php <address>\n";

if (PHP_SAPI !== 'cli') {
    echo "App must be run from command line\n";
    die(USAGE_INFO);
}

if (count($argv) < 2) {
    die(USAGE_INFO);
}

$startPage = $argv[1];

$app = new CrawlerApp($startPage);
$app->setCommand(new ImgTagCountCommand());
$app->run();
