<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteRead as SQLiteRead;
use App\SQLiteWrite as SQLiteWrite;
use App\ScanURLs as ScanURLs;
use App\BuildXML as BuildXML;

/**
 * Functions used prepare for and execute different scan types
*/
class ScanOptions {

  /**
  * Instantiated SQLiteRead class in class constructor
  * @var object
  */
  private $sqlread;

  /**
  * Instantiated SQLiteWrite class in class constructor
  * @var object
  */
  private $sqlwrite;

  /**
  * Instantiated ScanURLs class in class constructor
  * @var object
  */
  private $scan;

  /**
   * Class constructor method, instantiates common classes used in all functions and assigns them to properties
  */
  public function __construct() {
    $this->sqlread = new SQLiteRead();
    $this->sqlwrite = new SQLiteWrite();
    $this->scan = new ScanURLs;
  }

  /**
  * Method which triggers the "standard" web site scan. It takes a long time to run, but is more thorough than the "lite" scan.
  * Uses the locale and sets the initial pages to be scanned as the homepage for that locale, and the sitemap-p page.
  * The sitemap page is then used to gather all cities and build a list of "city rejects", from which all other pages can be compared.
  * Each page that is scanned will potentially update the links table with yet more links to scan, so the while loop has to check
  * if there are any links which need to be scanned each time before proceeding, using the SQLiteRead->checkLinksToWork method
  * Finally, build the XML file for the links gathered during the scan
  * @param $locale - the site locale, as selected on the HTML form
  */
  public function standardScan($locale, $filename) {
    // Set initial target urls
    $target = 'https://www.musement.com/'.$locale.'/';
    $seedurls = [
      array('url'=>$target.'sitemap-p/', 'type'=>'other', 'include'=>1),
      array('url'=>$target, 'type'=>'other', 'include'=>1)
    ];

    // insert target into list of links to scan
    $this->sqlwrite->insertLinks($seedurls);
    // gather the links you will use to begin the while loop
    $linksfound = $this->sqlread->retrieveLinks();
    if (count($linksfound) != 2) {
      throw new \Exception(
        "Incorrect number of links in starting table. Try deleting database and re-running scan from beginning."
      );
    }

    // set time limit for open connection to 50 minutes
    set_time_limit(5000);

    // while there are urls in the links table with worked == 0
    while (!empty($this->sqlread->checkLinksToWork())) {
    // while (array_search('0', array_column($linksfound, 'worked')) !== false) {
    // set the $lastlink var to the value of the last url in the array
      $lastlink = end($linksfound)['url'];
      // for every link in the links table
      foreach ($linksfound as $link) {
        // only process "unworked" links & "include" links
        if ($link['worked'] == 0 && $link['include'] == 1) {
          // scan & process
          $this->scan->scanURL($link['url'], $locale);
        }
        // if this is the last link in the array, rebuild the array with all the links found during last processing run
        if ($link['url'] == $lastlink) {
          // gather list of links in table
          $linksfound = $this->sqlread->retrieveLinks();
        }
      }
    }

    // Finished populating tables, now build xml
    // retrieve the full link list from the db for the final time
    $alllinks = (new SQLiteRead())->retrieveLinks();
    // create the xml file
    (new BuildXML($alllinks))->createXMLFile($filename);

  }

  /**
  * Method which triggers the "lite" web site scan. This does not take long to run, but is not very thorough. It uses the API to gather
  * all "top 20" cities and events, and simply scans these pages for other links. It does not then scan these pages, so does not know if
  * the pages are valid, or if ant subsequent pages are to be found.
  * Finally, build the XML file for the links gathered during the scan
  * @param $locale - the site locale, as selected on the HTML form
  */
  public function liteScan($locale, $filename) {
    // gather city urls only
    $cities = array_column($this->sqlread->retrieveCities(), 'url');
    // generate new array containing urls and city type for sending to links table
    $citylinks = array();
    foreach ($cities as $city) {
      $citylinks[] = array('url'=>$city, 'type'=>'city', 'include'=>1);
    }

    // gather activity urls only
    $events = array_column($this->sqlread->retrieveEvents(), 'url');
    // generate new array containing urls and city type for sending to links table
    $eventlinks = array();
    foreach ($events as $event) {
      $eventlinks[] = array('url'=>$event, 'type'=>'event', 'include'=>1);
    }

    // merge all city and activity urls together
    $toscan = array_merge($citylinks, $eventlinks);

    // insert all urls into links table ready for processing
    $this->sqlwrite->insertLinks($toscan);

    // validate starting data
    if (count($this->sqlread->retrieveLinks()) != 420) {
      throw new \Exception(
        "Incorrect number of links in starting table. Try deleting database and re-running scan from beginning."
      );
    }

    // set time limit for open connection to 50 minutes
    set_time_limit(3000);

    foreach ($this->sqlread->retrieveLinks() as $link) {
      // scan & process
      $this->scan->scanURL($link['url'], $locale);
    }

    // Finished populating tables, now build xml
    // retrieve the full link list from the db for the final time
    $alllinks = (new SQLiteRead())->retrieveLinks();
    // create the xml file
    try {
      (new BuildXML($alllinks))->createXMLFile($filename);
    } catch (Exception $e) {
      die( $e->__toString() );
    }

  }
}
