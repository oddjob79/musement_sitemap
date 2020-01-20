<?php
// enable use of namespaces
require 'vendor/autoload.php';

use App\SQLiteRead as SQLiteRead;
use App\SQLiteDBSetup as SQLiteDBSetup;
use App\ScanOptions as ScanOptions;
use App\BuildXML as BuildXML;

// Check to see if we have already selected and posted the selected region
if ($_POST['locale']) {
/**
* Code to start the process of running scan
*/
  // delete log file
  unlink('/vagrant/logs/php_errors.log');
  // set $locale from html form
  $locale = $_POST['locale'];
  $scantype = $_POST['version'];
  $filename = $_POST['filename'];

  // instantiate the SQLiteDBSetup class - deletes old db, creates new one with schema
  $dbsetup = new SQLiteDBSetup();
  // insert starting data into db (top 20 cities, top 20 activities, robot pages)
  $dbsetup->seedData($locale);

  // instantiate ScanOptions class
  $scanopt = new ScanOptions();
  // which scan level chosen
  if ($scantype == 'standard') {
    try {
      $scanopt->standardScan($locale);
    } catch (Exception $e) {
      die( $e->__toString() );
    }
  } elseif ($scantype == 'lite') {
    try {
      $scanopt->liteScan($locale);
    } catch (Exception $e) {
      die( $e->__toString() );
    }
  }

  // Finished populating tables, now build xml
  // instantiate the SQLiteRead class to read data from the database
  $sqlread = new SQLiteRead();
  // retrieve the full link list from the db for the final time
  $alllinks = $sqlread->retrieveLinks();
  // create the xml file
  // instantiate BuildXML class
  $bxml = new BuildXML($alllinks);
  try {
    $sitemapxml = $bxml->createXMLFile($filename);
  } catch (Exception $e) {
    die( $e->__toString() );
  }
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
      Select version of scan:
      <select name="version">
        <option value="standard">Standard</option>
        <option value="lite" selected="selected">Lite</option>
      </select>
    <br />
      XML Filename and (relative) path: <input type="text" name="filename">
    <br />
      <input type="submit" value="Scan Now"/>
    </form>
  </html>

<?php
}
?>
