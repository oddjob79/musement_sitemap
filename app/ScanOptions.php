<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteRead as SQLiteRead;
use App\SQLiteWrite as SQLiteWrite;
use App\ScanURLs as ScanURLs;

class ScanOptions {

  public function __construct() {
    $this->sqlread = new SQLiteRead();
    $this->sqlwrite = new SQLiteWrite();
    $this->scan = new ScanURLs;
  }

// Standard Scan - Can take quite a long time
  public function standardScan($locale) {
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

    // set time limit for open connection to 5 minutes
    set_time_limit(1000);

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
          $scan->scanURL($link['url'], $locale);
        }
        // if this is the last link in the array, rebuild the array with all the links found during last processing run
        if ($link['url'] == $lastlink) {
          // gather list of links in table
          $linksfound = $this->sqlread->retrieveLinks();
        }
      }
    }
  }

  // more lightweight scan with plenty of shortcuts - if you don't like waiting
  public function liteScan($locale) {
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

    set_time_limit(120);

    $counter = 0;
    foreach ($this->sqlread->retrieveLinks() as $link) {
      $counter++;
      error_log('Processing: '.$link['url'].'  Counter = '.$counter, 0);
      // scan & process
      $this->scan->scanURL($link['url'], $locale);
    }
  }
}
