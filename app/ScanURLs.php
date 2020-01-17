<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteRead as SQLiteRead;
use App\SQLiteWrite as SQLiteWrite;
use App\CurlDataRetrieval as CurlDataRetrieval;
use App\FilterManipulateData as FilterManipulateData;
use \DOMDocument as DOMDocument;

/**
 * cURL Functions
 */
class ScanURLs {

  // Additional functionality for sitemaps page. This page contains every city and city-category on the site.
  // Decided it was too wasteful to insert all this data only to reprocess and decide whether to ignore it.
  // Decided to cheat a little bit and manually use this to create a "city rejects" list to filter out other
  // links and save on processing time

  // private function scrapeLinks($xml, $sqlite) {
  private function scrapeLinks($pagecontent, $url) {
    // Create a new DOM Document to hold our webpage structure
    $xml = new DOMDocument();
    // set error level
    $internalErrors = libxml_use_internal_errors(true);
    // Load the url's contents into the DOM
    $xml->loadHTML($pagecontent);
    // Restore error level
    libxml_use_internal_errors($internalErrors);

    $newlinks = array();
    $citylinks = array();
    // Additional processing for sitemap only to retrieve city urls for reject list
    if (strpos($url, 'sitemap-p')) {
      // Find all links within <h3> tags on the HTML Page and return array of 'new links'. These are the 'city' links.
      // Specific to sitemap page as contains all city pages. Run at the beginning of the scrape
      foreach ($xml->getElementsByTagName('h3') as $head) {
        // Loop through each <a> tag in the h3 tag (should only ever be one)
        foreach($head->getElementsByTagName('a') as $link) {
          // isolate href attribute
          $url = $link->getAttribute('href');
          // add the newly found url to the array
          array_push($citylinks, $url);
        }
      }
    }
    // Loop through each <a> tag in the dom and add it to the links table
    foreach($xml->getElementsByTagName('a') as $link) {
      // isolate href attribute
      $url = $link->getAttribute('href');
      // add the newly found url to the array
      array_push($newlinks, $url);
    }
    // return both $citylinks and $newlinks arrays
    return array('newlinks'=>$newlinks, 'citylinks'=>$citylinks);
  }

  // controls how the given url is scanned and parsed before deciding whether and how to create a db record for it
  // public function scanURLs($linksfound, $sqlite) {
  public function scanURL($url, $locale) {
    error_log('Scanning '.$url, 0);
    // instantiate CurlDataRetrieval class
    $curl = new CurlDataRetrieval();
    // use curl to get page data
    $res = $curl->getPageData($url);

    // separate into page content (for links) and page info (for sitemap)
    $pageinfo = $res['info'];
    $pagecontent = $res['content'];

    // filter out html errors on scanned page
    // instantiate FilterManipulateData class
    $fmd = new FilterManipulateData();
    // send the pageinfo to the filterURLs method to evaluate whether to continue processing the url
    if ($fmd->isHTTPError($pageinfo) == 0) {
      return;
    }

    error_log('Scraping', 0);
    // scrape web page
    $alllinks = $this->scrapeLinks($pagecontent, $url);
    // split into two arrays. One for the new links found (standard)
    $newlinks = $alllinks['newlinks'];
    // The other for sitemap-p page only - get all the city links for special processing
    $citylinks = $alllinks['citylinks'];

    // instantiate SQLiteRead class
    $sqlread = new SQLiteRead();
    // find top 20 cities
    $topcities = array_column($sqlread->retrieveCities(), 'url');

    // instantiate SQLiteWrite class for use in foreach loop
    $sqlwrite = new SQLiteWrite();

    // write all links to links table
    $citylinkstoadd = array();
    foreach($citylinks as $city) {
      // if the city is NOT in the top 20 cities
      if (!in_array($city, $topcities)) {
        // push to the $citylinkstoadd array with include flag of 0
        array_push($citylinkstoadd, array('url'=>$city, 'type'=>'city', 'include'=>0));
        // write to the city reject list
        $sqlwrite->insertCityReject($city);
      } else {
        // else push to the $citylinkstoadd array with include flag of 1
        array_push($citylinkstoadd, array('url'=>$city, 'type'=>'city', 'include'=>1));
      }
    }
    // write all city links to table - consider merging with $linkstoadd and adding at the end
    $sqlwrite->insertLinks($citylinkstoadd);

    // set all relative links to absolute
    foreach ($newlinks as $key => $link) {
      $abslink = $fmd->relativeToAbsoluteLink($link);
      $newlinks[$key] = $abslink;
    }

    error_log('Filtering', 0);
    // filter out links previously in table
    // retrieve list of links already in table, and return the url column only
    $currlinks = array_column($sqlread->retrieveLinks(), 'url');
    // return only urls not already in links table (in $newlinks but not in $currlinks)
    $newlinks = array_diff($newlinks, $currlinks);

    // NEW LINK FILTERING

    // NOT TRUE BELOW
    // Links are added to the link list as 'Not Include' links, so they can be ignored during reprocessing, but also included when comparing to avoid re-capturing rejected links

    // set $cityrejects as array containing previously rejected cities
    $cityrejects = $sqlread->retrieveCityRejects();

    // set $linkstoadd as empty array
    $linkstoadd = array();

    // loop through $newlinks to decide whether to add the links to the db for processing / inclusion in sitemap
    foreach ($newlinks as $newlink) {
      // get the page type
      $viewtype = $fmd->findPageTypeFromURL($newlink);

      // if URL is non Musement.com - set include to 0
      $include = $fmd->checkURLPath($newlink, $locale);

      // is the URL set to be excluded by the robots.txt file?
      if ($include == 1) {
        $include = $fmd->checkRobotPages($newlink);
      }

      // does the url relate to a city on the city rejects list?
      // send array of cityrejects so don't have to re-query for each link
      if ($include == 1) {
        $include = $fmd->isCityReject($newlink, $cityrejects);
      }

      // Then, filter out all activities which are not in the top activities list
      // We have decided it is an activity, and it relates to a top 20 city. Now we check if it is a top 20 event, set to not include, if not
      if ($viewtype == 'event' && $include == 1) {
        $include = $fmd->isTop20Event($newlink);
      }

      // update $linkstoadd with the url, view type and include flag
      if ($include == 1) {
        $linkstoadd[] = array('url'=>$newlink, 'type'=>$viewtype, 'include'=>$include);
      }
    }
    error_log('Writing', 0);
    // insert links found on webpage to db as multi-dimensional array
    $sqlwrite->insertLinks($linkstoadd);
    $sqlwrite->updateLink($url, 1);
  }

}
