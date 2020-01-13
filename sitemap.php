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
echo '1';
$locale = 'es';
echo '2';
// Set initial target url(s)
$target = 'https://www.musement.com/'.$locale.'/';
// $target = array('https://www.musement.com/es/', 'https://www.musement.com/it/', 'https://www.musement.com/fr/');

echo '3';
// insert target into list of links to scan
$sqlite->insertLink($target);
echo '4';
// retrieve list of unworked links to scan
$linksfound = $sqlite->retrieveLinks();
echo '5';
// limit $linksfound for testing
$linksfound = array_slice($linksfound, 0, 50);
echo '6';
// instantiate scanning library
$scan = new CurlDataRetrieval();
echo '7';

// consider do while loop to test if there are any non worked links
// consider counting non worked links and setting i to the count, then re-test

// for each url in links table
foreach ($linksfound as $link) {
  $i=0;
  echo $link['url'].'<br />';
  while ($i<10) {
    // $scan->scanURL($link['url'], $sqlite);
    // var_dump($link);

    $i++;
  }
}
echo '8';

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
