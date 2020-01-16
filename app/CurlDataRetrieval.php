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
    // convert $locale to correct format. From "es" to "es-ES" for example
    $locale = $locale.'-'.strtoupper($locale);
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
    // if relative link then add protocol and domain (relative defined as none http and beginning with a slash)
    if (substr($url, 0, 4) != 'http' && substr($url, 0, 1) == '/') {
      $url = 'https://www.musement.com'.$url;
    }
    return $url;
  }

  private function removeNonMusementLinks($url, $locale) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // Only evaluate musement.com links
    if (parse_url($url, PHP_URL_HOST).substr(parse_url($url, PHP_URL_PATH), 0, 4) != 'www.musement.com/'.$locale.'/') {
      $validurl = 0;
    }

    return $validurl;
  }

  // filter out pages specified in robots.txt
  private function robotPages($url, $sqlite) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // find the path of the url being checked
    $path = parse_url($url, PHP_URL_PATH);
    // fetch the robot array pages. Stored in db so would not have to retrieve using curl each time
    $robarr = $sqlite->retrieveRobotPages();

    // does anything in the robots array match the end of the url?
    foreach ($robarr as $roburl) {
      // if the end of the url path equals the robot url
      if (substr($path, 0-strlen($roburl['url'])) == $roburl['url']) {
        $validurl = 0;
      }
    }

    return $validurl;
  }

  // TO DO - rename this method to something more suitable
  // filter run after scanning for http != 200
  private function filterURLs($pageinfo) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;

    // Only evaluate links which are valid http codes (filter after scanning & before writing)
    if ($pageinfo['http_code'] != 200) {
      $validurl = 0;
    }

    return $validurl;
  }

  // Special method for scrapeSiteMapLinks page. This page contains every city and city-category on the site.
  // Decided it was too wasteful to insert all this data only to reprocess and decide whether to ignore it.
  // Decided to cheat a little bit and manually use this to create a "city rejects" list to filter out other
  // links and save on processing time

  // Find all links within <h3> tags on the HTML Page and return array of 'new links'. These are the 'city' links.
  // Specific to siitemap page as contains all city pages. Run at the beginning of the scrape
  // private function scrapeLinks($xml, $sqlite) {
  private function scrapeSiteMapLinks($xml, $sqlite) {
    // find top 20 cities
    $topcities = array_column($sqlite->retrieveCities(), 'url');

    $newlinks = array();
    // loop through the h3 tags only (these are the city headers)
    foreach ($xml->getElementsByTagName('h3') as $head) {
      // Loop through each <a> tag in the dom
      foreach($head->getElementsByTagName('a') as $link) {
        // make sure link is absolute
        $url = $this->relativeToAbsoluteLink($link->getAttribute('href'));
        // set $include var as 1 by default
        $include = 1;
        // if the city is not in the top 20, add to rejects and move on
        if (!array_search($url, $topcities)) {
          $include = 0;
          $sqlite->insertCityReject($url);
          continue;
        }
      }
    }
    return $newlinks;
  }

  // Find all links on the HTML Page and return array of 'new links'
  // private function scrapeLinks($xml, $sqlite) {
  private function scrapeLinks($xml, $sqlite, $locale) {
    $newlinks = array();
    // Loop through each <a> tag in the dom and add it to the links table
    foreach($xml->getElementsByTagName('a') as $link) {
      // make sure link is absolute
      $url = $this->relativeToAbsoluteLink($link->getAttribute('href'));

      // remove any links which are not related the www.musement.com site
      if ($this->removeNonMusementLinks($url, $locale) != 1) {
        continue;
      }

      // remove any pages which the robots.txt file says to ignore
      if ($this->robotPages($url, $sqlite) != 1) {
        continue;
      }

      array_push($newlinks, $url);

    }
    return $newlinks;
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
  private function isTop20Event($url, $sqlite) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // retrieve top 20 event list from API
    $eventdata = $sqlite->retrieveEvents();

    // is the city one of the top 20 cities from the API?
    if (!array_search($url, array_column($eventdata, 'url'))) {
      // set $validurl to 0
      $validurl = 0;
    }
    return $validurl;
  }

  // controls how the given url is scanned and parsed before deciding whether and how to create a db record for it
  // public function scanURLs($linksfound, $sqlite) {
  public function scanURL($url, $sqlite, $locale) {
    // use curl to get page data
    $res = $this->getPageData($url);

    // separate into page content (for links) and page info (for sitemap)
    $pageinfo = $res['info'];
    $pagecontent = $res['content'];

    // filter out html errors
    if ($this->filterURLs($pageinfo) == 0) {
      $sqlite->setLinkToNotInclude($url);
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

    // if the page is the sitemap-p page, then send for special scanning for cities
    if (strpos($url, 'sitemap-p')) {
      $smcitylinks = $this->scrapeSiteMapLinks($xml, $sqlite);
      $sqlite->insertLinks($smcitylinks);
      error_log ('SiteMap Scraped', 0);
    }

    // scan the webpage for links
    $newlinks = $this->scrapeLinks($xml, $sqlite, $locale);
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
      $cityurl = $this->buildCityURL($newlink);
      // check to see if the $cityurl is in the $cityrejects list, set to not include, if so
      if (array_search($cityurl, array_column($cityrejects, 'url'))) {
        $include = 0;
      }

      // Then, filter out all activities which are not in the top activities list
      if ($viewtype == 'event') {
        // We have decided / assumed it is an activity. Now we check if it is a top 20 event, set to not include, if not
        if ($this->isTop20Event($newlink, $sqlite) == 0) {
          $include = 0;
        }
      }
      // update $linkstoadd with the url, view type and include flag
      $linkstoadd[] = array('url'=>$newlink, 'type'=>$viewtype, 'include'=>$include);
    }

    // insert links found on webpage to db as multi-dimensional array
    $sqlite->insertLinks($linkstoadd);

  }

  // @parameter $links contains array of links from db
  // return xml file(name?)
  // used https://programmerblog.net/how-to-generate-xml-files-using-php/ for help
  public function createXMLFile($links) {
    // specify file (and path) to be generated
    $file = 'sitemap.xml';
    // instantiate DOMDocument class and specify xml version and encoding
    $dom = new DOMDocument('1.0', 'utf-8');
    // set root element name
    $root = $dom->createElementNS('http://www.sitemaps.org/schemas/sitemap/0.9', 'urlset');

    // loop through the $links array and add elements into the xml
    foreach ($links as $link) {
      if ($link['include'] == 1) {
        // set loc attribute per link
        $linkloc = $link['url'];
        // depending on page type, set priorty
        switch ($link['type']) {
          case 'city':
            $linkpriority = '0.7';
            break;
          case 'event':
            $linkpriority = '0.5';
            break;
          default:
            $linkpriority = '1.0';
        }

        // create url element
        $url = $dom->createElement('url');
          // create loc element and append it to the url element
          $loc = $dom->createElement('loc', $linkloc);
          $url->appendChild($loc);
          // create priority element and append it to the url element
          $priority = $dom->createElement('priority', $linkpriority);
          $url->appendChild($priority);
        // append url element to root (urlset)
        $root->appendChild($url);
      }
    }

    // add root element
    $dom->appendChild($root);
    // save to file
    $dom->save($file);

    // format XML as HTML ready output string
    $xmloutput = $dom->saveXML();
    return $xmloutput;
  }
}
