<?php
// enable use of namespaces
require 'vendor/autoload.php';

// use classes for SQLite connection
use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteInteract as SQLiteInteract;
use App\CurlDataRetrieval as CurlDataRetrieval;

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

  // use createTables method to create the db tables, if they don't already exist
  $sqlite->createTables();
  // insert starting data into db (top 20 cities, top 20 activities, link types)
  $sqlite->seedData($locale);


  // START PROGRAM
  // Set initial target url(s)
  $target = 'https://www.musement.com/'.$locale.'/';
  // $target = array('https://www.musement.com/es/', 'https://www.musement.com/it/', 'https://www.musement.com/fr/');

  // $seedurls = array($target.'sitemap-p/', $target);

  $seedurls = [
    array('url'=>$target.'sitemap-p/', 'type'=>'other', 'include'=>1),
    array('url'=>$target, 'type'=>'other', 'include'=>1)
  ];

  // insert target into list of links to scan
  $sqlite->insertLinks($seedurls);

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

  set_time_limit(60);

  $x=0;
  // while there are urls in the links table with worked == 0
  while ($x<2 && array_search('0', array_column($linksfound, 'worked')) !== false) {
    $x++;

    // sort array by length of url - we should get cities first and can prefilter based on city
    // sort array by number of slashes found in the URL path in order to prioritize cities to aid prefiltering
    // usort($linksfound, function($a, $b) {
    //     // return strlen($a['url']) - strlen($b['url']);
    //     return substr_count(parse_url($a['url'], PHP_URL_PATH), '/') - substr_count(parse_url($b['url'], PHP_URL_PATH), '/');
    // });

    // set the $lastlink var to the value of the last url in the array
    $lastlink = end($linksfound)['url'];
    $counter = 0;

    foreach ($linksfound as $link) {
      // added only for logging and counting
      if ($link['worked']==0) {
        $counter++;
        error_log('Processing: '.$link['url'].'  Counter = '.$counter, 0);
        // filter out urls we don't need / want to scan and previously worked urls
        // if ($scan->preScanFilter($link['url'], $sqlite) != 0 && $link['worked'] == 0) {
          // error_log('Processing URL: '.$link['url'].' $x = '.$x, 0);
          // scan & process
          $scan->scanURL($link['url'], $sqlite);
          error_log($link['url'].' Scanning complete.');
        // }

      }

      // if this is the last link in the array, rebuild the array
      if ($link['url'] == $lastlink) {
        error_log('Last URL: '.$link['url'], 0);
        // gather list of links in table
        $linksfound = $sqlite->retrieveLinks();
      }
    }
  }




  echo '<br />Located Links:<br />';
  var_dump($linksfound);
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
