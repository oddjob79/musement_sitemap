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

  // Special method for scrapeSiteMapLinks page. This page contains every city and city-category on the site.
  // Decided it was too wasteful to insert all this data only to reprocess and decide whether to ignore it.
  // Decided to cheat a little bit and manually use this to create a "city rejects" list to filter out other
  // links and save on processing time

  // Find all links within <h3> tags on the HTML Page and return array of 'new links'. These are the 'city' links.
  // Specific to siitemap page as contains all city pages. Run at the beginning of the scrape
  // private function scrapeLinks($xml, $sqlite) {
  private function scrapeSiteMapLinks($xml, $fmd, $topcities) {
    // // instantiate SQLiteRead class
    // $sqlread = new SQLiteRead();
    // // find top 20 cities
    // $topcities = array_column($sqlread->retrieveCities(), 'url');

    // instantiate required classes prior to starting foreach loop
    // $sqlwrite = new SQLiteWrite();

    $newlinks = array();
    $rejectlist = array();
    // loop through the h3 tags only (these are the city headers)
    foreach ($xml->getElementsByTagName('h3') as $head) {
      // Loop through each <a> tag in the h3 tag (should only ever be one)
      foreach($head->getElementsByTagName('a') as $link) {
        // make sure link is absolute
        $url = $fmd->relativeToAbsoluteLink($link->getAttribute('href'));
        // set $include var as 1 by default
        $include = 1;
        // if the city is not in the top 20, add to rejects and move on
        if (!array_search($url, $topcities)) {
          $include = 0;
          // $sqlwrite->insertCityReject($url);
          array_push($rejectlist, $url);
        }
      }
      // build the $newlinks array with the city data for adding to the links table
      array_push($newlinks, array('url'=>$url, 'type'=>'city', 'include'=>1));
    }
    // write the newly found links to the links table
    // $sqlwrite->insertLinks($newlinks);
    // return array of newly found links and a list of rejected cities for writing to db
    return array('newlinks'=>$newlinks, 'rejects'=>$cityrejects);
  }

  // Find all links on the HTML Page and return array of 'new links'
  // private function scrapeLinks($xml, $sqlite) {
  private function scrapeLinks($xml, $fmd, $locale) {
    $newlinks = array();
    // Loop through each <a> tag in the dom and add it to the links table
    foreach($xml->getElementsByTagName('a') as $link) {
      // make sure link is absolute
      $url = $fmd->relativeToAbsoluteLink($link->getAttribute('href'));
      // remove any links which are not related the www.musement.com site
      if ($fmd->removeNonMusementLinks($url, $locale) != 1) {
        continue;
      }
      // remove any pages which the robots.txt file says to ignore
      if ($fmd->robotPages($url, $sqlite) != 1) {
        continue;
      }
      // add the newly found url to the array
      array_push($newlinks, $url);
    }
    return $newlinks;
  }

  // controls how the given url is scanned and parsed before deciding whether and how to create a db record for it
  // public function scanURLs($linksfound, $sqlite) {
  public function scanURL($url, $locale) {
    // instantiate CurlDataRetrieval class
    $curl = new CurlDataRetrieval();
    // use curl to get page data
    $res = $curl->getPageData($url);

    // separate into page content (for links) and page info (for sitemap)
    $pageinfo = $res['info'];
    $pagecontent = $res['content'];

    // filter out html errors
    // instantiate FilterManipulateData class
    $fmd = new FilterManipulateData();
    // send the pageinfo to the filterURLs method to evaluate whether to continue processing the url
    if ($fmd->filterURLs($pageinfo) == 0) {
      return;
    }

    // Create a new DOM Document to hold our webpage structure
    $xml = new DOMDocument();
    // set error level
    $internalErrors = libxml_use_internal_errors(true);
    // Load the url's contents into the DOM
    $xml->loadHTML($pagecontent);
    // Restore error level
    libxml_use_internal_errors($internalErrors);

    // instantiate SQLiteRead & SQLiteWrite classes
    $sqlread = new SQLiteRead();
    $sqlwrite = new SQLiteWrite();
    // if the page is the sitemap-p page (should be the first page to scan), then send for special scanning for cities
    if (strpos($url, 'sitemap-p')) {
      // find top 20 cities
      $topcities = array_column($sqlread->retrieveCities(), 'url');
      // scrape SiteMap page
      $res = $this->scrapeSiteMapLinks($xml, $fmd, $topcities);
      $citylinks = $res['newlinks'];
      $cityrejects = $res['rejects'];

      foreach ($cityrejects as $reject) {
        $sqlwrite->insertCityReject($reject);
      }
      $sqlwrite->insertLinks($citylinks);
    }

    // scan the webpage for links
    $newlinks = $this->scrapeLinks($xml, $fmd, $locale);


    // retrieve list of links already in table, and return the url column only
    $currlinks = array_column($sqlite->retrieveLinks(), 'url');
    // return only urls not already in links table (in $newlinks but not in $currlinks)
    $newlinks = array_diff($newlinks, $currlinks);

    // NEW LINK FILTERING
    // Links are added to the link list as 'Not Include' links, so they can be ignored during reprocessing, but also included when comparing to avoid re-capturing rejected links

    // set $cityrejects as array containing previously rejected cities
    $cityrejects = $sqlite->retrieveCityRejects();
    // set $linkstoadd as empty array
    $linkstoadd = array();
    // loop through $newlinks to decide whether to add the links to the db for processing / inclusion in sitemap
    foreach ($newlinks as $key => $newlink) {
      // Set view type for newly added link based on url format
      // extract path for ease of use
      $path = parse_url($newlink, PHP_URL_PATH);
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

      // Set $include as default = 1;
      $include = 1;
      // First up, filter out all links which have a rejected city 'stem'
      $cityurl = $fmd->buildCityURL($newlink);
      // check to see if the $cityurl is in the $cityrejects list, set to not include, if so
      if (array_search($cityurl, array_column($cityrejects, 'url'))) {
        $include = 0;
      }

      // Then, filter out all activities which are not in the top activities list
      if ($viewtype == 'event') {
        // We have decided / assumed it is an activity. Now we check if it is a top 20 event, set to not include, if not
        if ($fmd->isTop20Event($newlink, $sqlite) == 0) {
          $include = 0;
        }
      }
      // update $linkstoadd with the url, view type and include flag
      $linkstoadd[] = array('url'=>$newlink, 'type'=>$viewtype, 'include'=>$include);
    }

    // insert links found on webpage to db as multi-dimensional array
    $sqlite->insertLinks($linkstoadd);

  }

}
