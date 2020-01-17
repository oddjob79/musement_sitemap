<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteWrite as SQLiteWrite;
use App\SQLiteRead as SQLiteRead;
use \DOMDocument as DOMDocument;

/**
 * Contains methods related to filtering out unwanted data or manipulating during scan
 */
class FilterManipulateData {

  // Converts relative to Absolute links
  public function relativeToAbsoluteLink($url) {
    // if relative link then add protocol and domain (relative defined as none http and beginning with a slash)
    if (substr($url, 0, 4) != 'http' && substr($url, 0, 1) == '/') {
      $url = 'https://www.musement.com'.$url;
    }
    return $url;
  }

  public function checkURLPath($url, $locale) {
    // $include flag - set whether we include the link in the sitemap
    $include = 1;
    // Only evaluate musement.com links
    if (parse_url($url, PHP_URL_HOST).substr(parse_url($url, PHP_URL_PATH), 0, 4) != 'www.musement.com/'.$locale.'/') {
      $include = 0;
    }

    return $include;
  }

  // filter out pages specified in robots.txt
  public function checkRobotPages($url) {
    // $include flag - set whether we include the link in the sitemap
    $include = 1;
    // find the path of the url being checked
    $path = parse_url($url, PHP_URL_PATH);
    // instantiate SQLiteRead class
    $sqlread = new SQLiteRead();
    // fetch the robot array pages. Stored in db so would not have to retrieve using curl each time
    $robarr = $sqlread->retrieveRobotPages();

    // does anything in the robots array match the end of the url?
    foreach ($robarr as $roburl) {
      // if the end of the url path equals the robot url
      if (substr($path, 0-strlen($roburl['url'])) == $roburl['url']) {
        $include = 0;
      }
    }

    return $include;
  }

  // TO DO - rename this method to something more suitable
  // filter run after scanning for http != 200
  public function isHTTPError($pageinfo) {
    // instantiate SQLiteWrite class
    $sqlwrite = new SQLiteWrite();
    // $validurl will determine if we carry on processing url
    $validurl = 1;

    // Only evaluate links which are valid http codes (filter after scanning & before writing)
    if ($pageinfo['http_code'] != 200) {
      // Set the include flag to 0 in the links table
      $sqlwrite->updateLink($pageinfo['url'], 0);
      // set $validurl to 0 so we can stop processing this url
      $validurl = 0;
    }

    return $validurl;
  }

  // Takes the url passed to it and returns a url containing only the 'stem' (i.e. the url of the city the page relates to)
  public function buildCityURL($url) {
    // remove everything after the second element in the url path to see if it is a city page or the child of city page
    $path = parse_url($url, PHP_URL_PATH);
    $city = strstr(substr($path, 4), '/', true);
    // rebuild the url using the schema, the host, and the parsed path. $cityurl will be in the form of https://www.musement.com/<locale>/<city>/
    $cityurl = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).substr($path, 0,  4).$city.'/';

    return $cityurl;
  }

  // Determines if the URL is a "top 20" activity / event and returns var determining validity
  public function isTop20Event($url) {
    // $include flag - set whether we include the link in the sitemap
    $include = 1;
    // instantiate SQLiteRead class
    $sqlread = new SQLiteRead();
    // retrieve top 20 event list from API
    $eventdata = $sqlread->retrieveEvents();

    // is the city one of the top 20 cities from the API?
    if (!array_search($url, array_column($eventdata, 'url'))) {
      // set $validurl to 0
      $include = 0;
    }
    return $include;
  }

  public function findPageTypeFromURL($link) {
    $viewtype = '';
    // Set view type for newly added link based on url format
    // extract path for ease of use
    $path = parse_url($link, PHP_URL_PATH);
    // if last 2 characters before the last slash are one of '-p', '-v', '-t', '-l', '-c'
    if (in_array(substr($path, -3, 2), array('-p', '-v', '-t', '-l', '-c'))) {
      // it's an "other" type of page
      $viewtype = 'other';
    }
    // else if path contains 4 slashes and the last character before the last slash is numeric
    elseif (substr_count($path, '/') == 4 && is_numeric(substr($path, -2, 1))) {
      // It's an activity / event
      $viewtype = 'event';
    }
    // else if there are 3 slashes in the path and no dashes
    elseif (substr_count($path, '/') == 3 && !substr_count($path, '-')) {
      // It's a city
      $viewtype = 'city';
    } else {
      $viewtype = 'unknown';
    }

    return $viewtype;
  }

  // Filter out all links which have a rejected city 'stem'
  public function isCityReject($link, $cityrejects) {
    // $include flag - set whether we include the link in the sitemap
    $include = 1;
    // instantiate SQLiteRead class
    $sqlread = new SQLiteRead();
    // find the city this url relates to
    $cityurl = $this->buildCityURL($link);
    // check to see if the $cityurl is in the $cityrejects list, set to not include, if so
    if (array_search($cityurl, array_column($cityrejects, 'url'))) {
      $include = 0;
    }

    return $include;
  }

}