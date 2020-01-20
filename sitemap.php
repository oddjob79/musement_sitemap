<?php
// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteRead as SQLiteRead;
use App\SQLiteWrite as SQLiteWrite;
use App\SQLiteDBSetup as SQLiteDBSetup;
use App\ScanURLs as ScanURLs;
use App\BuildXML as BuildXML;

// Check to see if we have already selected and posted the selected region
if ($_POST['locale']) {

  function dbPrep() {
    // delete log file
    unlink('/vagrant/logs/php_errors.log');
    // set $locale from html form
    $locale = $_POST['locale'];
    $scantype = $_POST['version'];

    // instantiate the SQLiteDBSetup class - deletes old db, creates new one with schema
    $dbsetup = new SQLiteDBSetup();
    // insert starting data into db (top 20 cities, top 20 activities, robot pages)
    $dbsetup->seedData($locale);

  }

// Standard Scan - Can take quite a long time
  function standardScan($locale, $sqlread, $sqlwrite, $scan) {
    // Set initial target urls
    $target = 'https://www.musement.com/'.$locale.'/';
    $seedurls = [
      array('url'=>$target.'sitemap-p/', 'type'=>'other', 'include'=>1),
      array('url'=>$target, 'type'=>'other', 'include'=>1)
    ];

    // insert target into list of links to scan
    $sqlwrite->insertLinks($seedurls);
    // gather the links you will use to begin the while loop
    $linksfound = $sqlread->retrieveLinks();

    // set time limit for open connection to 5 minutes
    set_time_limit(1000);

    // while there are urls in the links table with worked == 0
    while (!empty($sqlread->checkLinksToWork())) {
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
          $linksfound = $sqlread->retrieveLinks();
        }
      }
    }
  }
  // end of standardScan

  // more lightweight scan with plenty of shortcuts - if you don't like waiting
  function liteScan($locale, $sqlread, $sqlwrite, $scan) {
    // gather city urls only
    $cities = array_column($sqlread->retrieveCities(), 'url');
    // generate new array containing urls and city type for sending to links table
    $citylinks = array();
    foreach ($cities as $city) {
      $citylinks[] = array('url'=>$city, 'type'=>'city', 'include'=>1);
    }

    // gather activity urls only
    $events = array_column($sqlread->retrieveEvents(), 'url');
    // generate new array containing urls and city type for sending to links table
    $eventlinks = array();
    foreach ($events as $event) {
      $eventlinks[] = array('url'=>$event, 'type'=>'event', 'include'=>1);
    }

    // merge all city and activity urls together
    $toscan = array_merge($citylinks, $eventlinks);

    // insert all urls into links table ready for processing
    $sqlwrite->insertLinks($toscan);

    set_time_limit(120);

    $counter = 0;
    foreach ($sqlread->retrieveLinks() as $link) {
      $counter++;
      error_log('Processing: '.$link['url'].'  Counter = '.$counter, 0);
      // scan & process
      $scan->scanURL($link['url'], $locale);
    }
  }
// end of liteScan


/**
* Code to start the process of running scan
*/
  // delete old db, create a new one and seed it with starting data
  dbPrep();
  // instantiate the SQLiteWrite class to write data to the database
  $sqlwrite = new SQLiteWrite();
  // instantiate the SQLiteRead class to read data from the database
  $sqlread = new SQLiteRead();
  // instantiate scanning library for use inside the foreach loop
  $scan = new ScanURLs();

  if ($scantype == 'standard') {
    standardScan($locale, $sqlread, $sqlwrite, $scan);
  } elseif ($scantype == 'lite') {
    liteScan($locale, $sqlread, $sqlwrite, $scan);
  }


  // Finished populating tables, now build xml
  // retrieve the full link list from the db for the final time
  $alllinks = $sqlread->retrieveLinks();
  // create the xml file
  // instantiate BuildXML class
  $bxml = new BuildXML($alllinks);
  $sitemapxml = $bxml->createXMLFile();
  // output to browser
  header('Content-Type: text/xml');
  echo $sitemapxml;

} else {

    // html form for selecting locale and version
  ?>
  <html>
    <h2>Musement.com sitemap generator</h2>
    <h4>Built by Robert Turner</h4>
    <form method="post" action="sitemap.php">
      Please choose your region:
      <select name="locale">
        <option value=""></option>
        <option value="es">es-ES</option>
        <option value="it">it-IT</option>
        <option value="fr">fr-FR</option>
      </select>
    <br />
      Select version:
      <select name="version">
        <option value="standard">Standard</option>
        <option value="lite" selected="selected">Lite</option>
      </select>
    <br />
      <input type="submit" value="Scan Now"/>
    </form>
  </html>

<?php
}
?>
