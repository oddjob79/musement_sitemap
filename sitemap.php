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
// $target = array('https://www.musement.com/es/', 'https://www.musement.com/it/', 'https://www.musement.com/fr/');

// insert target into list of links to scan
$sqlite->insertLink($target);

// retrieve list of unworked links to scan
$linksfound = $sqlite->retrieveLinks();
// limit $linksfound for testing
$linksfound = array_slice($linksfound, 0, 50);

// instantiate scanning library
$scan = new CurlDataRetrieval();
// for each url in masterlinks
foreach ($linksfound as $url) {
  $scan->scanURL($url, $sqlite);
}



echo '<br />Located Links:<br />';
var_dump($linksfound);

?>
