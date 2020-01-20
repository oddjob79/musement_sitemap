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
 * Functions used to work through the scanning of each web page
 */
class ScanURLs {

  /**
  * Instantiated classes of objects used in many functions in this class
  * @var object
  */
  private $sqlread;
  private $sqlwrite;
  private $fmd;

  /**
   * Class constructor method, instantiates common classes used in all functions and assigns them to properties
  */
  public function __construct() {
    $this->sqlread = new SQLiteRead();
    $this->sqlwrite = new SQLiteWrite();
    $this->fmd = new FilterManipulateData();
  }


  // Additional functionality for sitemaps page. This page contains every city and city-category on the site.
  // Decided it was too wasteful to insert all this data only to reprocess and decide whether to ignore it.
  // Decided to cheat a little bit and manually use this to create a "city rejects" list to filter out other
  // links and save on processing time

  /**
  * Method takes the page content, and using a created DOM Document object, loads the content as HTML to analyse. It then loops
  * through the page content looking for <a> links and populating an array with the results. Contains special functionality
  * for the sitemap page which contains city links only in the <h3> tags. Builds a separate array with this data, when relevant
  * and returns both.
  * @param array $pagecontent - the content of the web page as gathered by the curl_exec command
  * @param string $url - the URL to be scraped
  * @return array - contains all new links (<a href="">) found on the webpage, as well as an array containing all
  * links relating to a city, if the page analysed was the sitemap.
  */
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


  /**
  * Used only for the sitemap page. Takes an array containing all the cities scraped from the page, and check to see if they
  * are in our "top 20" list. If they are then, add them into our list of links with an include flag of 1, so they can be included
  * in our sitemap. Otherwise, add them with an include flag of 0, and add the url to the "city_rejects" table, so related URLs
  *  can be easily identified and excluded.
  * @param array $citylinks - array containing all links scraped from the sitemap page
  */
  private function cityLinksProcessing($citylinks) {
    // find top 20 cities
    $topcities = array_column($this->sqlread->retrieveCities(), 'url');
    // declare array
    $citylinkstoadd = array();
    // for every "city" link found on the sitemap page
    foreach($citylinks as $city) {
      // if the city is NOT in the top 20 cities
      if (!in_array($city, $topcities)) {
        // push to the $citylinkstoadd array with include flag of 0
        array_push($citylinkstoadd, array('url'=>$city, 'type'=>'city', 'include'=>0));
        // Then write to the city reject list
        $this->sqlwrite->insertCityReject($city);
      } else {
        // else, its a top 20 city, so push to the $citylinkstoadd array with include flag of 1
        array_push($citylinkstoadd, array('url'=>$city, 'type'=>'city', 'include'=>1));
      }
    }
    // write all city links to db
    $this->sqlwrite->insertLinks($citylinkstoadd);
  }

  /**
  * Runs through a list of URLs found on a web page, and performs a series of checks to see whether the URL should be
  * included in the XML output. Calls the following methods:
  * FilterManipulateData->relativeToAbsoluteLink() - converts the link to absolute, if not already
  * FilterManipulateData->findPageTypeFromURL() - designate page type for link
  * FilterManipulateData->checkURLPath() - checks URL is musement.com and for the given locale
  * FilterManipulateData->checkRobotPages() - checks if the URL is on the robots.txt page
  * FilterManipulateData->isCityReject() - is the URL related to a non top 20 city
  * FilterManipulateData->isTop20Event() - is the URL an event and a non top 20 activity
  * Returns an array containing the data to be written to the database, based on the result of these checks
  * @param array $newlinks - array containing all links found in the scanned web page
  * @param string $locale - the locale for which the XML is being generated
  * @return array $linkstoadd - Based on the filter, this contains the URL, the view type it has been assigned and
  * an include flag to designate whether it should be included on the XML output
  */
  private function newLinkFiltering($newlinks, $locale) {
    error_log('Filtering', 0);

    // set all relative links to absolute
    foreach ($newlinks as $key => $link) {
      $abslink = $this->fmd->relativeToAbsoluteLink($link);
      $newlinks[$key] = $abslink;
    }

    // filter out links previously in table
    // retrieve list of links already in table, and return the url column only
    $currlinks = array_column($this->sqlread->retrieveLinks(), 'url');
    // return only urls not already in links table (in $newlinks but not in $currlinks)
    $newlinks = array_diff($newlinks, $currlinks);

    // set $cityrejects as array containing previously rejected cities
    $cityrejects = $this->sqlread->retrieveCityRejects();

    // set $linkstoadd as empty array
    $linkstoadd = array();

    // loop through $newlinks to decide whether to add the links to the db for processing / inclusion in sitemap
    foreach ($newlinks as $newlink) {
      // get the page type
      $viewtype = $this->fmd->findPageTypeFromURL($newlink);

      // if URL is non Musement.com - set include to 0
      $include = $this->fmd->checkURLPath($newlink, $locale);

      // is the URL set to be excluded by the robots.txt file?
      if ($include == 1) {
        $include = $this->fmd->checkRobotPages($newlink);
      }

      // does the url relate to a city on the city rejects list?
      // send array of cityrejects so don't have to re-query for each link
      if ($include == 1) {
        $include = $this->fmd->isCityReject($newlink, $cityrejects);
      }

      // Then, filter out all activities which are not in the top activities list
      // We have decided it is an activity, and it relates to a top 20 city. Now we check if it is a top 20 event, set to not include, if not
      if ($viewtype == 'event' && $include == 1) {
        $include = $this->fmd->isTop20Event($newlink);
      }

      // update $linkstoadd with the url, view type and include flag
      if ($include == 1) {
        $linkstoadd[] = array('url'=>$newlink, 'type'=>$viewtype, 'include'=>$include);
      }
    }
    return $linkstoadd;
  }

  /**
  * Meat and bones of the application. This method takes the URL to be scanned, as well as the locale being used, and does the following:
  * Retrieves the web page data and information, drops pages which have HTTP errors, then scans the page for links.
  * If the page was the sitemap, send to cityLinksProcessing for special processing
  * Sends the new links to newLinkFiltering method for conversion to absolute links, and to filter out duplicates, non musement Links,
  * robot links, urls related to non top 20 cities and non top 20 events.
  * New links are then added to the links table and the scanned link is updated to show it has been 'worked'
  * @param string $url - this is the URL which will be scanned
  * @param string $locale - the locale for which the XML is being generated
  */
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
    // send the pageinfo to the filterURLs method to evaluate whether to continue processing the url
    if ($this->fmd->isHTTPError($pageinfo) == 0) {
      return;
    }

    error_log('Scraping', 0);
    // scrape web page
    $alllinks = $this->scrapeLinks($pagecontent, $url);
    // split into two arrays. One for the new links found (standard)
    $newlinks = $alllinks['newlinks'];
    // The other for sitemap-p page only - get all the city links for special processing
    $citylinks = $alllinks['citylinks'];

    // if the page is the sitemap, send for special processing
    if (strpos($url, 'sitemap-p')) {
      $this->cityLinksProcessing($citylinks);
    }

    // send all newly found links off for filtering
    $linkstoadd = $this->newLinkFiltering($newlinks, $locale);

    error_log('Writing', 0);
    // insert links found on webpage to db as multi-dimensional array
    $this->sqlwrite->insertLinks($linkstoadd);
    $this->sqlwrite->updateLink($url, 1);
  }

}
