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

// instantiate scanning library
$scan = new CurlDataRetrieval();

// Set locale (probably scrap)
$locale = 'es';

// gather city urls only
$cities = array_column($sqlite->retrieveCities(), 'url');
// generate new array containing urls and city type for sending to links table
$citylinks = array();
foreach ($cities as $city) {
  $citylinks[] = array('url'=>$city, 'type'=>'city', 'include'=>1);
}

// gather activity urls only
$events = array_column($sqlite->retrieveEvents(), 'url');
// generate new array containing urls and city type for sending to links table
$eventlinks = array();
foreach ($events as $event) {
  $eventlinks[] = array('url'=>$event, 'type'=>'event', 'include'=>1);
}

// merge all city and activity urls together
// $toscan = array_merge($cities, $events);
$toscan = array_merge($citylinks, $eventlinks);

// insert all urls into links table ready for processing
$sqlite->insertLinks($toscan);


set_time_limit(60);

$counter = 0;
foreach ($sqlite->retrieveLinks() as $link) {
  $counter++;
  error_log('Processing: '.$link['url'].'  Counter = '.$counter, 0);
  // scan & process
  $scan->scanURL($link['url'], $sqlite);
}

$linklist = $sqlite->retrieveLinks();
foreach ($linklist as $link) {
  if ($link['include']==1) {
    echo '<br />'.$link['url'].'<br />';
    if ($link['type']=='city') {
      echo 'Priority: 0.7<br />';
    } elseif ($link['type']=='event') {
      echo 'Priority: 0.5<br />';
    } else {
      echo 'Priority: 1.0<br />';
    }

  }

}

// Original logic below

// // START PROGRAM
// // Set locale (probably scrap)
// $locale = 'es';
// // Set initial target url(s)
// $target = 'https://www.musement.com/'.$locale.'/';
// // $target = array('https://www.musement.com/es/', 'https://www.musement.com/it/', 'https://www.musement.com/fr/');
//
// $seedurls = array($target.'sitemap-p/', $target);
//
// // insert target into list of links to scan
// $sqlite->insertLinks($seedurls);

// set_time_limit(120);
// $linksfound = $sqlite->retrieveLinks();

// $x=0;
// // while there are urls in the links table with worked == 0
// while ($x<3 && array_search('0', array_column($linksfound, 'worked')) !== false) {
//   $x++;
//
//   // set the $lastlink var to the value of the last url in the array
//   $lastlink = end($linksfound)['url'];
//   $counter = 0;
//
//   foreach ($linksfound as $link) {
//     // added only for logging and counting
//     if ($link['worked']==0) {
//       $counter++;
//       error_log('Processing: '.$link['url'].'  Counter = '.$counter, 0);
//         // scan & process
//         $scan->scanURL($link['url'], $sqlite);
//         error_log($link['url'].' Scanning complete.');
//       // }
//
//     }
//
//     // if this is the last link in the array, rebuild the array
//     if ($link['url'] == $lastlink) {
//       error_log('Last URL: '.$link['url'], 0);
//       // gather list of links in table
//       $linksfound = $sqlite->retrieveLinks();
//     }
//   }
// }
//


// 
// echo '<br />Located Links:<br />';
// var_dump($linksfound);

?>
