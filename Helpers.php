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
}

class CurlWrapper {

    protected $_ch;

    public function __construct() {
        $this->_ch = curl_init();
        curl_setopt($this->_ch, CURLOPT_RETURNTRANSFER, true);
    }

    public function __destruct() {
        curl_close($this->_ch);
    }

    public function get($url) {
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        return curl_exec($this->_ch);
    }

    public function getEffectiveUrl($url) {
        curl_setopt($this->_ch, CURLOPT_URL, $url);
        if (curl_exec($this->_ch) !== false) {
            return curl_getinfo($this->_ch, CURLINFO_EFFECTIVE_URL);
        }
        return false;
    }
}

class ParsedPagesCache {

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
}

class HtmlPage {

    protected $_domDoc;

    public function __construct() {
        $this->_domDoc = new \DOMDocument();
        libxml_use_internal_errors(true);
    }

    public function load($html) {
        $this->_domDoc->loadHTML($html, LIBXML_COMPACT);
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
        return '';
    }

}