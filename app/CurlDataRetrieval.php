<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteInteract as SQLiteInteract;
use \DOMDocument as DOMDocument;

/**
 * cURL Functions
 */
class CurlDataRetrieval {

  // Uses API URL and locale to retrieve data from the API and send back an array of json elements
  // @param string $apiurl
  // @param string $locale
  // @return array $output containing data retrieved from the API
  public function getAPIData($apiurl, $locale) {
    // $url = 'https://api.musement.com/api/v3/cities';
    $ch = curl_init($apiurl);
    // allows a string to be set to the result of curl_exec
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    // set locale and content type in header
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Accept-Language: '.$locale.'\'',
      'Content-Type: application/json'
    ]);
    $res = curl_exec($ch);
    curl_close($ch);

    // move the results into an array
    $output = json_decode($res, true);

    return $output;

  }

  // use curl to retrieve page content and information for specified url
  function getPageData($url) {
    // create curl resource
    $ch = curl_init();
    // set url
    curl_setopt($ch, CURLOPT_URL, $url);
    //return the transfer as a string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // Retrieve last modified file time
    curl_setopt($ch, CURLOPT_FILETIME, true);
    // $output contains the output string
    $output = curl_exec($ch);
    // use curl_getinfo to get information about the resource
    $info = curl_getinfo($ch);
    // close curl resource to free up system resources
    curl_close($ch);

    return array('info'=>$info, 'content'=>$output);
  }

  private function relativeToAbsoluteLink($url) {
    // if relative link then add protocol and domain
    if (substr($url, 0, 4) != 'http') {
      $url = 'https://www.musement.com'.$url;
    }
    return $url;
  }

  // filter run after scanning
  private function preScanFilter($url) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // Only evaluate musement.com links
    if (substr(parse_url($url, PHP_URL_HOST), -12) != 'musement.com') {
      $validurl = 0;
    }

    // filter out pages specified in robots.txt
    // use curl to get robots.txt info
    $res = getPageData('https://www.musement.com/robots.txt');
    // build array containing disallowed pages
    $robarr = explode("Disallow: /*", str_replace("\n", "", $res['content']));
    // remove the other text from robots.txt from array
    array_shift($robarr);
    // var_dump($arr);
    // find the path of the url being checked
    $path = parse_url($url, PHP_URL_PATH);
    // does anything in the robots array match the end of the url?
    foreach ($robarr as $roburl) {
      if (substr($path, 0-strlen($roburl)) == $roburl) {
        $validurl = 0;
      }
    }

    // Filter out unwanted locales
    $localearr = array('es','it','fr');
    if (!in_array(substr($path, 1, 2), $localearr)) {
      $validurl = 0;
    }

    return $validurl;
  }

  // filter run after scanning
  private function filterURLs($pageinfo) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // Only evaluate links which are valid http codes (filter after scanning & before writing)
    if ($pageinfo['http_code'] != 200) {
      $validurl = 0;
    }

    return $validurl;
  }

  // Find all links on the HTML Page and return array of 'new links'
  // private function scrapeLinks($xml, $sqlite) {
  private function scrapeLinks($xml) {
    $newlinks = array();
    // Loop through each <a> tag in the dom and add it to the links table
    foreach($xml->getElementsByTagName('a') as $link) {
      // if link is a mailto link or a tel link - ignore it
      if (substr($link->getAttribute('href'), 0, 7) != 'mailto:' && substr($link->getAttribute('href'), 0, 4) != 'tel:') {
        error_log('NEW LINK FOUND = '.$link->getAttribute('href'), 0);
        // make sure link is absolute
        $url = $this->relativeToAbsoluteLink($link->getAttribute('href'));
        error_log('relative link created for: '.$url);
        // // insert url into db
        // $sqlite->insertLink($url);
        array_push($newlinks, $url);
      }
    }
    return $newlinks;
  }

  // receives $xml object and returns the 'view' - what type of page it is
  // @param $xml object
  // return $view (page type)
  private function scrapeView($xml) {
    // define $state and $view  as empty strings
    $state = ''; $view = '';

    // locate the window.__INITIAL_STATE__ script which contains page details
    foreach($xml->getElementsByTagName('script') as $script) {
      if (substr($script->textContent, 0, 24) == 'window.__INITIAL_STATE__') {
        // remove beginning and end of string so you are left with json only - this is the "state" of the page
        $state = substr($script->textContent, 25, -122);
        continue;
      }
    }
    // if $state exists then set $view
    if ($state != '') {
      // decode json string
      $stateinfo = json_decode($state);
      // returns the view value (contains the page type)
      $view = $stateinfo->state->router->view;
    }

    return $view;
  }

  // pass html content from web page and return an array of links
  // adapted from example on PHP.NET/manual given by Jay Gilford
  private function parseContent($content, $sqlite) {
    // Create a new DOM Document to hold our webpage structure
    $xml = new DOMDocument();
    // set error level
    $internalErrors = libxml_use_internal_errors(true);
    // Load the url's contents into the DOM
    $xml->loadHTML($content);
    // Restore error level
    libxml_use_internal_errors($internalErrors);

    // scrape the HTML Page for links and add them to the links table
    // $this->scrapeLinks($xml, $sqlite);
    $newlinks = $this->scrapeLinks($xml);
    // retrieve list of links already in table, and return the url column only
    $currlinks = array_column($sqlite->retrieveLinks(), 'url');
    // return only urls not already in links table
    $newlinks = array_diff($newlinks, $currlinks);

    // insert links as array
    $sqlite->insertLinks($newlinks);

    // parses the html for the view / page type
    $view = $this->scrapeView($xml);

    //Return the view / page type
    return $view;
  }

  // Determines if the URL related to a "top 20" city and returns var determining validity
  private function isTop20City($url, $sqlite) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // it's a city-related page. Find the city it relates to.
    // remove everything after the second element in the url path to see if it is a city page or the child of city page
    $path = parse_url($url, PHP_URL_PATH);
    $city = strstr(substr($path, 4), '/', true);
    // rebuild the url using the schema, the host, and the parsed path. $cityurl will be in the form of https://www.musement.com/<locale>/<city>/
    $cityurl = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).substr($path, 0,  4).$city.'/';
    // retrieve top 20 city list from API
    $citydata = $sqlite->retrieveCities();

    // is the city one of the top 20 cities from the API?
    if (!array_search($cityurl, array_column($citydata, 'url'))) {
      // UPDATE link list to 'worked' and set to 'not include' for non-top 20 city
      // $sqlite->setLinkToWorked($url);
      // $sqlite->setLinkToNotInclude($url);
      $validurl = 0;
    }
    error_log($url.'city function, validurl = '.$validurl);

    return $validurl;
  }

  // Determines if the URL is a "top 20" activity / event and returns var determining validity
  private function isTop20Event($url, $sqlite) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // retrieve top 20 event list from API
    $eventdata = $sqlite->retrieveEvents();

    // is the city one of the top 20 cities from the API?
    if (!array_search($url, array_column($eventdata, 'url'))) {
      // UPDATE link list to 'worked' and set to 'not include' for non-top 20 city
      // $sqlite->setLinkToWorked($url);
      // $sqlite->setLinkToNotInclude($url);
      $validurl = 0;
    }
    return $validurl;
  }

  // uses a list of links found and a list of previously scanned urls to determine which urls to scan for new links
  // three levels of filtering:
  // pages you should not scan at all (already been found and scanned)
  // pages you have discarded after scanning (non http 200s)
  // pages that should be written but not searched for additional links (non-musemennt.com pages)
  // public function scanURLs($linksfound, $sqlite) {
  public function scanURL($url, $sqlite) {
    // use curl to get page data
    $res = $this->getPageData($url);
    error_log($url . ' scanned.<br />', 0);
    // separate into page content (for links) and page info (for sitemap)
    $pageinfo = $res['info'];
    $pagecontent = $res['content'];

    // filter out unwanted pages (html errors and non musement pages)
    $validurl = $this->filterURLs($pageinfo);

    error_log($url . ' filtered. Valid: '.$validurl.'<br />', 0);

    // Send page content to function to return only information which will be used
    // Parse HTML. Insert all links found into links table, and return the page type (view)
    // define $viewtype as empty string as default
    $viewtype = '';
    if ($validurl == 1) {
      $viewtype = $this->parseContent($pagecontent, $sqlite);
      error_log($url . ' parsed.<br />', 0);
    }

    // Now we have the links, check to see if it's a city-related page.
    // city, event, attraction, editorial
    if (in_array($viewtype, array('city', 'event', 'attraction', 'editorial'))) {
      //  Now see if it's (related to) a top 20 city.
      $validurl = $this->isTop20City($url, $sqlite);
      error_log($url . ' cityfiltered. Valid: '.$validurl.'<br />', 0);
      //  We know it's related to a top 20 city, now see if it's a top 20 activity / event.
      if ($viewtype == 'event') {
        $validurl = $this->isTop20Event($url, $sqlite);
        error_log($url . ' activityfiltered. Valid: '.$validurl.'<br />', 0);
      }
    }

    // Refactor to use only one update sql query

    // Decide what to do with the links
    // The url has been scanned, so set url to 'worked'
    // $sqlite->setLinkToWorked($url);
    // was the url valid? Is there a view specified in the page state? If so, update the view
    if ($validurl == 1 && $viewtype != '') {
      $sqlite->setLinkPageType($url, $viewtype);
    } else {
      // otherwise update the include column to 0
      $sqlite->setLinkToNotInclude($url);
    }
  }


}
