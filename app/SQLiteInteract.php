<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\CurlDataRetrieval as CurlDataRetrieval;


/**
 * SQLite Interact with Database
 */
 // Change to DB Setup queries only
class SQLiteInteract {

  /**
   * PDO object
   * @var \PDO
   */
  private $pdo;

  /**
   * connect to the SQLite database
   */
  public function __construct($pdo) {
      $this->pdo = $pdo;
  }

  // // enable foreign key constraints - INEXPLICABLY NOT WORKING!!!!!!!!!
  // public function enableForeignKeys() {
  //   // enable foreign key constraints
  //   $command = 'PRAGMA foreign_keys=1;';
  //   $this->pdo->exec($command);
  // }

  /**
   * create tables
   */
  public function createTables() {
      $commands = ['CREATE TABLE IF NOT EXISTS cities (
                      id INTEGER PRIMARY KEY,
                      name TEXT NOT NULL,
                      url TEXT NOT NULL
                    )',
          'CREATE TABLE IF NOT EXISTS events (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  uuid TEXT NOT NULL,
                  title TEXT NOT NULL,
                  url TEXT NOT NULL,
                  city_id INTEGER NOT NULL)',
          'CREATE TABLE IF NOT EXISTS types (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  type TEXT NOT NULL)',
          'CREATE TABLE IF NOT EXISTS links (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  url TEXT NOT NULL,
                  type INTEGER NOT NULL DEFAULT 0,
                  include INTEGER NOT NULL DEFAULT 1,
                  worked INTEGER NOT NULL DEFAULT 0)'
          ];
      // execute the sql commands to create new tables
      foreach ($commands as $command) {
          $this->pdo->exec($command);
      }
  }

  // Inserts city id and url into cities table
  // @param int $id
  // @param string $url
  private function insertCities() {
    // echo '<br />Size of city array = ', sizeof($this->retrieveCities());
    // check if data already exists, if so, exit
    if (sizeof($this->retrieveCities()) == 20) {
      return;
    }
    // set vars for data retrieval
    $cityapiurl = 'https://api.musement.com/api/v3/cities?limit=20';
    $locale = 'es-ES';
    // retrieve city data from api
    $citydata = (new CurlDataRetrieval())->getAPIData($cityapiurl, $locale);
    // run through each city array to insert to db
    foreach ($citydata as $city) {
      // prepare sql statement
      $sql = 'INSERT INTO cities(id, name, url) VALUES(:id, :name, :url)';
      $stmt = $this->pdo->prepare($sql);
      try {
        // execute sql insert statement
        $stmt->execute([
                    ':id' => $city['id'],
                    ':name' => $city['name'],
                    ':url' => $city['url'],
                    ]);
      } catch (Exception $e) {
        echo 'Error writing to DB: ',  $e->getMessage(), "\n";
      }
    }
  }

  // retrieves all citydata
  // return array containing city id, name, url
  public function retrieveCities() {
    // prepare select statement
    $stmt = $this->pdo->query('SELECT id, name, url FROM cities');
    // create empty $citydata object
    $citydata = [];
    // fetch data from statement
    try {
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        // update with rows of data
        $citydata[] = [
          'id' => $row['id'],
          'name' => $row['name'],
          'url' => $row['url']
        ];
      }
    } catch (Exception $e) {
      echo 'Error retrieving data: ',  $e->getMessage(), "\n";
    }
    return $citydata;
  }

  private function insertEvents() {
    // retrieve top 20 cities
    $citydata = $this->retrieveCities();
    // check if data already exists, if so, exit TO DO change to check size of events table
    if (sizeof($this->retrieveEvents()) == 400) {
      return;
    }
    // retrieve top 20 activities per city and insert into database
    foreach ($citydata as $city) {
      // set vars for data retrieval
      $eventapiurl = 'https://api.musement.com/api/v3/cities/'.$city['id'].'/activities?limit=20';
      $locale = 'es-ES';
      // retrieve event data from api for this city
      $eventdata = (new CurlDataRetrieval())->getAPIData($eventapiurl, $locale);
      foreach ($eventdata['data'] as $event) {
        // prepare sql statement
        $sql = 'INSERT INTO events(uuid, title, url, city_id) VALUES(:uuid, :title, :url, :city_id)';
        $stmt = $this->pdo->prepare($sql);
        try {
          // execute sql insert statement
          $stmt->execute([
                      ':uuid' => $event['uuid'],
                      ':title' => $event['title'],
                      ':url' => $event['url'],
                      ':city_id' => $city['id'],
                      ]);
        } catch (Exception $e) {
          echo 'Error writing to DB: ',  $e->getMessage(), "\n";
        }
      }
    }
  }

  // retrieves all event data
  // return array containing event id, uuid, title, url, city_id
  public function retrieveEvents() {
    // prepare select statement
    $stmt = $this->pdo->query('SELECT id, uuid, title, url, city_id FROM events');
    // create empty $eventdata object
    $eventdata = [];
    // fetch data from statement
    try {
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        // update with rows of data
        $eventdata[] = [
          'id' => $row['id'],
          'uuid' => $row['uuid'],
          'title' => $row['title'],
          'url' => $row['url'],
          'city_id' => $row['city_id']
        ];
      }
    } catch (Exception $e) {
      echo 'Error retrieving data: ',  $e->getMessage(), "\n";
    }
    return $eventdata;
  }

  private function insertTypes() {
    $typedata = array('unknown', 'city', 'event', 'attraction', 'editiorial', 'external', 'other');
    // run through each city array to insert to db
    foreach ($typedata as $type) {
      // prepare sql statement
      $sql = 'INSERT INTO types(type) VALUES(:type)';
      $stmt = $this->pdo->prepare($sql);
      try {
        // execute sql insert statement
        $stmt->execute([':type' => $type]);
      } catch (Exception $e) {
        echo 'Error writing to DB: ',  $e->getMessage(), "\n";
      }
    }
  }

  public function seedData() {
    $this->insertCities();
    $this->insertEvents();
    $this->insertTypes();
  }

  // takes $urls array and uses data to update the links table
  public function insertLinks($urls) {
    foreach ($urls as $url) {
      // check link exists in the links table and continue if not
      if (!$this->checkLinkExists($url)) {
        // prepare sql statement
        $sql = 'INSERT INTO links(url) VALUES(:url)';
        $stmt = $this->pdo->prepare($sql);
        try {
          // execute sql insert statement
          $stmt->execute([':url' => $url]);
        } catch (Exception $e) {
          echo 'Error writing to DB: ',  $e->getMessage(), "\n";
        }
      }
    }
  }

  // returns all links found from site as array
  public function retrieveLinks() {
    // prepare select statement
    $stmt = $this->pdo->query('SELECT id, url, type, include, worked FROM links');
    // create empty $eventdata object
    $linkdata = [];
    // fetch data from statement
    try {
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        // update with rows of data
        $linkdata[] = [
          'id' => $row['id'],
          'url' => $row['url'],
          'type' => $row['type'],
          'include' => $row['include'],
          'worked' => $row['worked']
        ];
      }
    } catch (Exception $e) {
      echo 'Error retrieving data: ',  $e->getMessage(), "\n";
    }
    return $linkdata;
  }

  public function checkLinkExists($url) {
    // prepare select statement
    $stmt = $this->pdo->prepare('SELECT id FROM links WHERE url = :url');
    try {
      // execute sql insert statement
      $stmt->execute([':url' => $url]);
    } catch (Exception $e) {
      echo 'Error writing to DB: ',  $e->getMessage(), "\n";
    }
    $linkdata = [];
    while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
      // update with rows of data
      $linkdata[] = [
        'id' => $row['id']
      ];
    }
    return $linkdata;
  }

}
?>
