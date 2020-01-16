<?php
// enable use of namespaces
require 'vendor/autoload.php';

// use classes for SQLite connection
use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteInteract as SQLiteInteract;
use App\CurlDataRetrieval as CurlDataRetrieval;

// Check to see if we have already selected and posted the selected region
if ($_POST['locale']) {
  // delete log file
  unlink('/vagrant/logs/php_errors.log');
  // connect to sqlite db, or create if not exists, use the $init flag to designate as the initial connection and delete existing db
  $pdo = (new SQLiteConnection())->connect($init=1);
  // instantiate the SQLiteInteract class
  $sqlite = new SQLiteInteract($pdo);

  // instantiate scanning library
  $scan = new CurlDataRetrieval();

  // set $locale from html form
  $locale = $_POST['locale'];

  // use createTables method to create the db tables, if they don't already exist
  $sqlite->createTables();
  // insert starting data into db (top 20 cities, top 20 activities, link types)
  $sqlite->seedData($locale);

  // START PROGRAM
  // Set initial target urls
  $target = 'https://www.musement.com/'.$locale.'/';
  $seedurls = [
    array('url'=>$target.'sitemap-p/', 'type'=>'other', 'include'=>1),
    array('url'=>$target, 'type'=>'other', 'include'=>1)
  ];

  // insert target into list of links to scan
  $sqlite->insertLinks($seedurls);

  // instantiate scanning library
  $scan = new CurlDataRetrieval();

  $linksfound = $sqlite->retrieveLinks();

  set_time_limit(60);

  // while there are urls in the links table with worked == 0
  while (array_search('0', array_column($linksfound, 'worked')) !== false) {
    // set the $lastlink var to the value of the last url in the array
    $lastlink = end($linksfound)['url'];
    // for every link in the links table
    foreach ($linksfound as $link) {
      // only process "unworked" links
      if ($link['worked']==0) {
        // scan & process
        $scan->scanURL($link['url'], $sqlite, $locale);
      }
      // if this is the last link in the array, rebuild the array with all the links found during last processing run
      if ($link['url'] == $lastlink) {
        // gather list of links in table
        $linksfound = $sqlite->retrieveLinks();
      }
    }
  }

  // Finished populating tables, now build xml
  // retrieve the full link list from the db for the final time
  $linklist = $sqlite->retrieveLinks();
  // create the xml file
  $sitemapxml = $scan->createXMLFile($linklist);
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
