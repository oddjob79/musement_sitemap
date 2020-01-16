<?php
// enable use of namespaces
require 'vendor/autoload.php';

// use classes for SQLite connection
use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteInteract as SQLiteInteract;
use App\CurlDataRetrieval as CurlDataRetrieval;

// if html form has been completed and a locale has been sent

if ($_POST['locale']) {
  // delete log file
  unlink('/vagrant/logs/php_errors.log');
  // connect to sqlite db, or create if not exists, use the $init flag to designate as the initial connection and delete existing db
  $pdo = (new SQLiteConnection())->connect($init=1);
  // instantiate the SQLiteInteract class
  $sqlite = new SQLiteInteract($pdo);

  // instantiate scanning library
  $scan = new CurlDataRetrieval();

  $locale = $_POST['locale'];
  // echo '<locale>'.$locale.'</locale>';

  // use createTables method to create the db tables, if they don't already exist
  $sqlite->createTables();
  // insert starting data into db (top 20 cities, top 20 activities, link types)
  $sqlite->seedData($locale);

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
    $scan->scanURL($link['url'], $sqlite, $locale);
  }

  $linklist = $sqlite->retrieveLinks();
  $sitemapxml = $scan->createXMLFile($linklist);
  header('Content-Type: text/xml');
  // echo htmlspecialchars(file_get_contents($sitemapxml));
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
