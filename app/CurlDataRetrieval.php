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
    // if relative link then add protocol and domain
    if (substr($url, 0, 4) != 'http') {
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

  private function robotPages($url, $sqlite) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;

    // filter out pages specified in robots.txt
    // use curl to get robots.txt info
    // $res = $this->getPageData('https://www.musement.com/robots.txt');
    // // build array containing disallowed pages
    // $robarr = explode("Disallow: /*", str_replace("\n", "", $res['content']));
    // // remove the other text from robots.txt from array
    // array_shift($robarr);
    // var_dump($arr);

    // find the path of the url being checked
    $path = parse_url($url, PHP_URL_PATH);
    // fetch the robot array pages
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

  // filter run after scanning
  public function preScanFilter($url, $sqlite) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // // filter out non www.musement.com links
    // if ($this->removeNonMusementLinks($url) == 0) {
    //   $validurl = 0;
    // };
    //
    // // filter out any pages included in the robots.txt file
    // if ($this->robotPages($url, $sqlite) == 0) {
    //   $validurl = 0;
    // }

    // // Filter out unwanted locales
    // $localearr = array('es','it','fr');
    // if (!in_array(substr($path, 1, 2), $localearr)) {
    //   $validurl = 0;
    // }

    // filter out any previously rejected (non-top 20) cities
    // return only the city portion of the url
    $cityurl = $this->buildCityURL($url);
    // retrieve previously rejected city urls from db
    $cityrejects = $sqlite->retrieveCityRejects();
    // check to see if the $cityurl is in the $cityrejects list and if so, set the url to invalid
    if (array_search($cityurl, array_column($cityrejects, 'url'))) {
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

  // Find all links on the HTML Page and return array of 'new links'.
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

    // // retrieve previously rejected city urls from db
    // $cityrejects = $sqlite->retrieveCityRejects();
    //
    // // now run through all links on page
    // foreach($xml->getElementsByTagName('a') as $link) {
    //   // make sure link is absolute
    //   $url = $this->relativeToAbsoluteLink($link->getAttribute('href'));
    //
    //   // filter out any previously rejected (non-top 20) cities
    //   // return only the city portion of the url
    //   $cityurl = $this->buildCityURL($url);
    //   // check to see if the $cityurl is in the $cityrejects list and if so, move on
    //   if (array_search($cityurl, array_column($cityrejects, 'url'))) {
    //     continue;
    //   }
    //   // perform preWriteChecks on link before submitting it for db write
    //   if ($this->removeNonMusementLinks($url) == 1 && $this->robotPages($url, $sqlite) == 1) {
    //     array_push($newlinks, $url);
    //   }
    // }
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

      // perform preWriteChecks on link before submitting it for db write
      if ($this->removeNonMusementLinks($url, $locale) != 1) {
        continue;
      }

      if ($this->robotPages($url, $sqlite) != 1) {
        continue;
      }

      array_push($newlinks, $url);

      // if ($this->removeNonMusementLinks($url) == 1 && $this->robotPages($url, $sqlite) == 1) {
      //   array_push($newlinks, $url);
      // }
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
      $view = '';
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


  public function buildCityURL($url) {
    // remove everything after the second element in the url path to see if it is a city page or the child of city page
    $path = parse_url($url, PHP_URL_PATH);
    $city = strstr(substr($path, 4), '/', true);
    // rebuild the url using the schema, the host, and the parsed path. $cityurl will be in the form of https://www.musement.com/<locale>/<city>/
    $cityurl = parse_url($url, PHP_URL_SCHEME).'://'.parse_url($url, PHP_URL_HOST).substr($path, 0,  4).$city.'/';

    return $cityurl;
  }

  // Determines if the URL related to a "top 20" city and returns var determining validity
  private function isTop20City($url, $viewtype, $sqlite) {
    // $validurl will determine if we carry on processing url
    $validurl = 1;
    // it's a city-related page. Find the city it relates to.
    $cityurl = $this->buildCityURL($url);
    // retrieve top 20 city list from API
    $citydata = $sqlite->retrieveCities();

    // is the city one of the top 20 cities from the API?
    if (!array_search($cityurl, array_column($citydata, 'url'))) {
      // set $validurl flag to false
      $validurl = 0;
      // if the page is a city type page
      if ($viewtype=='city') {
        // write url to city_rejects table so any url relating to this city will be ignored in future
        $sqlite->insertCityReject($url);
      }
    }
    // error_log($url.'city function, validurl = '.$validurl);

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
      // set $validurl to 0
      $validurl = 0;
    }
    return $validurl;
  }

  // // pass html content from web page and return an array of links
  // // adapted from example on PHP.NET/manual given by Jay Gilford
  // private function parseContent($url, $content, $sqlite, $validurl) {
  //   // Create a new DOM Document to hold our webpage structure
  //   $xml = new DOMDocument();
  //   // set error level
  //   $internalErrors = libxml_use_internal_errors(true);
  //   // Load the url's contents into the DOM
  //   $xml->loadHTML($content);
  //   // Restore error level
  //   libxml_use_internal_errors($internalErrors);
  //
  //   // parses the html for the view / page type
  //   $viewtype = $this->scrapeView($xml);
  //
  //
  //   // Now we have the view information, check to see if it's a city-related page.
  //   // city, event, attraction, editorial
  //   if (in_array($viewtype, array('city', 'event', 'attraction', 'editorial'))) {
  //     //  Now see if it's (related to) a top 20 city.
  //     $validurl = $this->isTop20City($url, $viewtype, $sqlite);
  //     // error_log($url . ' cityfiltered. Valid: '.$validurl.'<br />', 0);
  //     //  We know it's related to a top 20 city, now see if it's a top 20 activity / event.
  //     if ($viewtype == 'event') {
  //       $validurl = $this->isTop20Event($url, $sqlite);
  //       // error_log($url . ' activityfiltered. Valid: '.$validurl.'<br />', 0);
  //     }
  //   }
  //
  //   // if the page has not been designated invalid, scrape the page for links, and insert the results
  //   if ($validurl == 1) {
  //     // scrape the HTML Page for links and add them to the links table
  //     // $this->scrapeLinks($xml, $sqlite);
  //     $newlinks = $this->scrapeLinks($xml, $sqlite);
  //     // retrieve list of links already in table, and return the url column only
  //     $currlinks = array_column($sqlite->retrieveLinks(), 'url');
  //     // return only urls not already in links table (in $newlinks but not in $currlinks)
  //     $newlinks = array_diff($newlinks, $currlinks);
  //     error_log('Scraped: '.$url.', '.count($newlinks).' found', 0);
  //
  //     // insert links as array
  //     $sqlite->insertLinks($newlinks);
  //   }
  //
  //   //Return the view / page type
  //   return $viewtype;
  // }

  // uses a list of links found and a list of previously scanned urls to determine which urls to scan for new links
  // three levels of filtering:
  // pages you should not scan at all (already been found and scanned)
  // pages you have discarded after scanning (non http 200s)
  // pages that should be written but not searched for additional links (non-musemennt.com pages)
  // public function scanURLs($linksfound, $sqlite) {
  public function scanURL($url, $sqlite, $locale) {
    // use curl to get page data
    $res = $this->getPageData($url);
    // error_log($url . ' scanned.<br />', 0);
    // separate into page content (for links) and page info (for sitemap)
    $pageinfo = $res['info'];
    $pagecontent = $res['content'];

    // filter out unwanted pages (html errors and non musement pages)
    // $validurl = $this->filterURLs($pageinfo);
    if ($this->filterURLs($pageinfo) == 0) {
      $sqlite->setLinkToNotInclude($url);
      return;
    }

    // error_log($url . ' filtered. Valid: '.$validurl.'<br />', 0);

    // First - establish if we should scrape the page for links
    // Find out what kind of page it is. Parse Content

    // Send page content to function to return only information which will be used
    // Parse HTML. Insert all links found into links table, and return the page type (view)

    // define $viewtype as empty string as default
    // $viewtype = '';
    // if ($validurl == 1) {
    //   $viewtype = $this->parseContent($url, $pagecontent, $sqlite, $validurl);
    //   // error_log($url . ' parsed. View = '.$viewtype.'<br />', 0);
    // }
    // Create a new DOM Document to hold our webpage structure
    $xml = new DOMDocument();
    // set error level
    $internalErrors = libxml_use_internal_errors(true);
    // Load the url's contents into the DOM
    $xml->loadHTML($pagecontent);
    // Restore error level
    libxml_use_internal_errors($internalErrors);

  // parses the html for the view / page type
  // $viewtype = $this->scrapeView($xml);
  // error_log ($url. ' View Scraped', 0);



    // // Now we have the view information, check to see if it's a city-related page.
    // // city, event, attraction, editorial
    // if (in_array($viewtype, array('city', 'event', 'attraction', 'editorial'))) {
    //   error_log ($url. ' View Scraped', 0);
    //   //  Now see if it's (related to) a top 20 city.
    //   if ($this->isTop20City($url, $viewtype, $sqlite) == 0) {
    //     $sqlite->setLinkToNotInclude($url);
    //     return;
    //   }
    //   // error_log($url . ' cityfiltered. Valid: '.$validurl.'<br />', 0);
    //   //  We know it's related to a top 20 city, now see if it's a top 20 activity / event.
    //   if ($viewtype == 'event') {
    //     if ($this->isTop20Event($url, $sqlite) == 0) {
    //       $sqlite->setLinkToNotInclude($url);
    //       return;
    //     }
    //     // error_log($url . ' activityfiltered. Valid: '.$validurl.'<br />', 0);
    //   }
    // }

    // if the page has not been designated invalid, scrape the page for links, and insert the results
    // scrape the HTML Page for links and add them to the links table
    // $this->scrapeLinks($xml, $sqlite);

    if (strpos($url, 'sitemap-p')) {
      $smcitylinks = $this->scrapeSiteMapLinks($xml, $sqlite);
      $sqlite->insertLinks($smcitylinks);
      error_log ('SiteMap Scraped', 0);
    }

    $newlinks = $this->scrapeLinks($xml, $sqlite, $locale);
    // retrieve list of links already in table, and return the url column only
    $currlinks = array_column($sqlite->retrieveLinks(), 'url');
    // return only urls not already in links table (in $newlinks but not in $currlinks)
    $newlinks = array_diff($newlinks, $currlinks);
    error_log('Links Scraped. '.count($newlinks).' new link(s) found', 0);

    // New link filtering.
    // Change so that links are not unset, but added to the link list as 'Not Include' links, then ignored during reprocessing
    // set $cityrejects as array containing previously rejected cities
    $cityrejects = $sqlite->retrieveCityRejects();

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
      // 1. Filter out all links which have a rejected city 'stem'
      $cityurl = $this->buildCityURL($newlink);
      // check to see if the $cityurl is in the $cityrejects list
      if (array_search($cityurl, array_column($cityrejects, 'url'))) {
        // remove from array and move onto the next link
        // unset($newlinks[$key]);
        $include = 0;
        // continue;
      }

      // 2. Filter out all activities which are not in the top activities list
      if ($viewtype == 'event') {
        // We have decided / assumed it is an activity. Now we check if it is a top 20 event
        if ($this->isTop20Event($newlink, $sqlite) == 0) {
          // remove from array and move onto the next link
          // unset($newlinks[$key]);
          $include = 0;
          // continue;
        }
      }
      $linkstoadd[] = array('url'=>$newlink, 'type'=>$viewtype, 'include'=>$include);
    }
    error_log('New Links Filtered. '.count($linkstoadd).' new link(s) remaining', 0);

    // insert links as array
    // $sqlite->insertLinks($newlinks);
    $sqlite->insertLinks($linkstoadd);

    // url has made it through to the end of scraping without being filtered out. Set the view type and worked = 1
    // $sqlite->setLinkPageType($url, $viewtype);

    // Refactor to use only one update sql query

    // Decide what to do with the links
    // The url has been scanned, so set url to 'worked'
    // $sqlite->setLinkToWorked($url);
    // was the url valid? Is there a view specified in the page state? If so, update the view
    // if ($validurl == 1) {
    //   // error_log('VALID url and updated: '.$url, 0);
    //   $sqlite->setLinkPageType($url, $viewtype);
    // } else {
    //   // otherwise update the include column to 0
    //   // error_log('INVALID url and updated: '.$url, 0);
    //   $sqlite->setLinkToNotInclude($url);
    // }
  }

  // @parameter contains array of links from db
  // return xml file(name?)
  // used https://programmerblog.net/how-to-generate-xml-files-using-php/ for help
  public function createXMLFile($links) {
    // specify file (and path) to be generated
    $file = 'sitemap.xml';
    // instantiate DOMDocument class and specify xml version and encoding
    $dom = new DOMDocument('1.0', 'utf-8');
    // set root element name
    // $root = $dom->createElement('urlset');
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
