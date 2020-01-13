<?php
// enable use of namespaces
require 'vendor/autoload.php';

// use classes for SQLite connection
use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteInteract as SQLiteInteract;

// connect to sqlite db, or create if not exists
$pdo = (new SQLiteConnection())->connect();
// instantiate the SQLiteInteract class
$sqlite = new SQLiteInteract($pdo);

// use createTables method to create the db tables, if they don't already exist
$sqlite->createTables();
// insert starting data into db (top 20 cities, top 20 activities, link types)
$sqlite->seedData();

// Create the sitempa sqlite database, create the tables and populate them with data from the API

// NOT SURE WHAT THIS WAS INTENDED FOR
// // connect to sqlite db, or create if not exists
// $pdo = (new SQLiteConnection())->connect();
// // instantiate the SQLiteInteract class
// $sqlite = new SQLiteInteract($pdo);
// // $cities = $sqlite->retrieveCities();

// START PROGRAM
// Set locale (probably scrap)
$locale = 'es';
// Set initial target url(s)
$target = array('https://www.musement.com/'.$locale.'/');

// insert target into list of links to scan
$sqlite->insertLinks($target);
// retrieve list of links
$linksfound = $sqlite->retrieveLinks();

// while (!empty(array_diff($linksfound, $workedurls)) && $i<2) {
//   echo '<br />Loop number '.$i.'<br />';
//   $output = scanURLs($linksfound, $workedurls);
//   $linksfound = $output['newlinks'];
//   $workedurls = $output['written'];
//   $i++;
// }

echo '<br />Located Links:<br />';
var_dump($linksfound);

?>
