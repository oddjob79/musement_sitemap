<?php
// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteRead as SQLiteRead;
use App\SQLiteWrite as SQLiteWrite;
use App\SQLiteDBSetup as SQLiteDBSetup;
use App\ScanURLs as ScanURLs;

// Check to see if we have already selected and posted the selected region
if ($_POST['locale']) {
  // delete log file
  unlink('/vagrant/logs/php_errors.log');
  // connect to sqlite db, or create if not exists, use the $init flag to designate as the initial connection and delete existing db
  $pdo = (new SQLiteConnection())->connect($init=1);
  // instantiate the SQLiteDBSetup class
  $dbsetup = new SQLiteDBSetup($pdo);
  // use createTables method to create the db tables, if they don't already exist
  $dbsetup->createTables();
  // insert starting data into db (top 20 cities, top 20 activities, link types)
  $dbsetup->seedData($locale);

  // set $locale from html form
  $locale = $_POST['locale'];

  // Set initial target urls
  $target = 'https://www.musement.com/'.$locale.'/';
  $seedurls = [
    array('url'=>$target.'sitemap-p/', 'type'=>'other', 'include'=>1),
    array('url'=>$target, 'type'=>'other', 'include'=>1)
  ];

  // instantiate the SQLiteWrite class to write data to the database
  $sqlwrite = new SQLiteWrite($pdo);
  // insert target into list of links to scan
  $sqlwrite->insertLinks($seedurls);
  // instantiate the SQLiteRead class to read data from the database
  $sqlread = new SQLiteRead($pdo);
  // gather the links you will use to begin the while loop
  $linksfound = $sqlread->retrieveLinks();

  // instantiate scanning library for use inside the foreach loop
  $scan = new ScanURLs();

  // set time limit for open connection to 5 minutes
  set_time_limit(300);

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
        $linksfound = $sqlread->retrieveLinks();
      }
    }
  }

  // Finished populating tables, now build xml
  // retrieve the full link list from the db for the final time
  $alllinks = $sqlread->retrieveLinks();
  // create the xml file
  // instantiate BuildXML class
  $bxml = new BuildXML();
  $sitemapxml = $bxml->createXMLFile($alllinks);
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
