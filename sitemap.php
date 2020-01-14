<?php
// enable use of namespaces
require 'vendor/autoload.php';

// use classes for SQLite connection
use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteInteract as SQLiteInteract;
use App\CurlDataRetrieval as CurlDataRetrieval;

// connect to sqlite db, or create if not exists
$pdo = (new SQLiteConnection())->connect();
// instantiate the SQLiteInteract class
$sqlite = new SQLiteInteract($pdo);

// use createTables method to create the db tables, if they don't already exist
$sqlite->createTables();
// insert starting data into db (top 20 cities, top 20 activities, link types)
$sqlite->seedData();


// START PROGRAM
// Set locale (probably scrap)
$locale = 'es';
// Set initial target url(s)
$target = 'https://www.musement.com/'.$locale.'/';
// $target = array('https://www.musement.com/es/', 'https://www.musement.com/it/', 'https://www.musement.com/fr/');

// insert target into list of links to scan
$sqlite->insertLink($target);

// instantiate scanning library
$scan = new CurlDataRetrieval();

// consider do while loop to test if there are any non worked links
// consider counting non worked links and setting i to the count, then re-test

// DO NOT RUN WITHOUT ADDITIONAL CHECKS - ADD DEBUGGING TO CHECK IT IS REQUERYING THE TABLE - ADD LIMITER TO STOP IT GOING MENTAL
$linksfound = $sqlite->retrieveLinks();

// Successful test to see if there are unworked urls in the $linksfound array
// if (array_search('0', array_column($linksfound, 'worked')) !== false) {
//   echo 'There are unworked urls';
// }
//
// echo '<br />end linksfound = '. end($linksfound)['url'];
$i=0; $x=0;
while ($x<5 && $i<5 && array_search('0', array_column($linksfound, 'worked')) !== false) {
  $x++;
  $lastlink = end($linksfound)['url'];
  foreach ($linksfound as $link) {
    $i++;
    // filter out urls we don't need / want to scan
    if ($scan->preScanFilter($link)!=0) {
      error_log('Processing URL: '.$link['url'], 0);
      // scan & process
      $scan->scanURL($link['url'], $sqlite);
    }
    if ($link['url'] == $lastlink) {
      error_log('Last URL: '.$link['url'], 0);
      $linksfound = $sqlite->retrieveLinks();
    }
  }
}


// while ($linksfound = $sqlite->retrieveLinks() && $i<5) {
// while ($linksfound) {
//   var_dump($linksfound);
//                 // // limit $linksfound for testing
//                 // $linksfound = array_slice($linksfound, 0, 50);
//
//   // for each url in links table
//   foreach ($linksfound as $link) {
//     echo 'This is the url sent for processing: '.$link['url'];
//     // scan & process
//     $scan->scanURL($link['url'], $sqlite);
//   }
// }




// error_log('first instance of linksfound = '.var_dump($linksfound), 0);
//
// foreach ($linksfound as $link) {
//   error_log('This is the url sent for processing: '.$link['url'], 0);
//   // scan & process
//   $scan->scanURL($link['url'], $sqlite);
// }
//
// $morelinksfound = $sqlite->retrieveLinks();
//
// error_log('second instance of linksfound = '.var_dump($morelinksfound), 0);



// foreach ($linksfound as $link) {
//   error_log('This is the url sent for processing: '.$link['url'], 0);
//   // scan & process
//   $scan->scanURL($link['url'], $sqlite);
// }








// // test output
// $writelinks = $sqlite->retrieveLinks();
// var_dump($linksfound);
// foreach ($writelinks as $link) {
//   var_dump($link);
//   if (($link['worked']==1) && ($link['include']==1)) {
//     echo 'Here\'s an outputted link: '. $link['url'];
//   }
// }



echo '<br />Located Links:<br />';
var_dump($linksfound);

?>
