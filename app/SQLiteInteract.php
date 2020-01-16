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
      $commands = [
          'CREATE TABLE IF NOT EXISTS cities (
                  id INTEGER PRIMARY KEY,
                  name TEXT NOT NULL,
                  url TEXT NOT NULL)',

          'CREATE TABLE IF NOT EXISTS events (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  uuid TEXT NOT NULL,
                  title TEXT NOT NULL,
                  url TEXT NOT NULL,
                  city_id INTEGER NOT NULL)',

          'CREATE TABLE IF NOT EXISTS links (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  url TEXT NOT NULL UNIQUE,
                  type TEXT,
                  include INTEGER NOT NULL DEFAULT 1,
                  worked INTEGER NOT NULL DEFAULT 0)',
          'CREATE TABLE IF NOT EXISTS city_rejects (
                  id INTEGER PRIMARY KEY,
                  url TEXT NOT NULL)',
          'CREATE TABLE IF NOT EXISTS robot_pages (
                  id INTEGER PRIMARY KEY,
                  url TEXT NOT NULL)'
          ];
      // execute the sql commands to create new tables
      foreach ($commands as $command) {
          $this->pdo->exec($command);
      }
  }

  // Inserts city id and url into cities table
  // @param int $id
  // @param string $url
  private function insertCities($locale) {
    // echo '<br />Size of city array = ', sizeof($this->retrieveCities());
    // check if data already exists, if so, exit - TO DO Update for all locales (60) - don't need as deleting db each run
    // if (sizeof($this->retrieveCities()) == 20) {
    //   return;
    // }
    // set vars for data retrieval
    $cityapiurl = 'https://api.musement.com/api/v3/cities?limit=20';
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

  private function insertEvents($locale) {
    // retrieve top 20 cities
    $citydata = $this->retrieveCities();
    // // check if data already exists, if so, exit TO DO update for all locales (1200) - do not need as deleting db each time
    // if (sizeof($this->retrieveEvents()) == 400) {
    //   return;
    // }
    // retrieve top 20 activities per city and insert into database
    foreach ($citydata as $city) {
      // set vars for data retrieval
      $eventapiurl = 'https://api.musement.com/api/v3/cities/'.$city['id'].'/activities?limit=20';
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

  public function seedData($locale) {
    $this->insertCities($locale);
    $this->insertEvents($locale);
    $this->insertRobotPages();
  }

  // takes $urls array and uses data to update the links table
  public function insertLink($url) {
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

  // // takes $urls array and uses data to update the links table
  // public function insertLinks($urls) {
  //   foreach ($urls as $url) {
  //     // prepare sql statement
  //     $sql = 'INSERT INTO links(url) VALUES(:url)';
  //     $stmt = $this->pdo->prepare($sql);
  //     try {
  //       // execute sql insert statement
  //       $stmt->execute([':url' => $url]);
  //     } catch (Exception $e) {
  //       echo 'Error writing to DB: ',  $e->getMessage(), "\n";
  //     }
  //   }
  // }

  // takes $urls array and uses data to update the links table
  public function insertLinks($links) {
    foreach ($links as $link) {
      // prepare sql statement
      $sql = 'INSERT INTO links(url, type, include) VALUES(:url, :type, :include)';
      $stmt = $this->pdo->prepare($sql);
      try {
        // execute sql insert statement
        $stmt->execute([
          ':url' => $link['url'],
          ':type' => $link['type'],
          ':include' => $link['include']
        ]);
      } catch (Exception $e) {
        echo 'Error writing to DB: ',  $e->getMessage(), "\n";
      }
    }
  }

  // returns all UNWORKED links found from site as array
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
      // execute sql select statement
      $stmt->execute([':url' => $url]);
    } catch (Exception $e) {
      echo 'Error querying DB: ',  $e->getMessage(), "\n";
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

  public function setLinkToWorked($url) {
    // Prepare SQL Update Statement for setting link to 'worked'
    $stmt = $this->pdo->prepare('UPDATE links SET worked = 1 where url = :url');
    // try to execute sql update statement
    try {
      $stmt->execute([':url' => $url]);
    } catch (Exception $e) {
      echo 'Error querying DB: ',  $e->getMessage(), "\n";
    }
  }

  public function setLinkToNotInclude($url) {
    // Prepare SQL Update Statement for setting link to 'not included'
    $stmt = $this->pdo->prepare('UPDATE links SET include = 0, worked = 1 where url = :url');
    // try to execute sql update statement
    try {
      $stmt->execute([':url' => $url]);
    } catch (Exception $e) {
      echo 'Error querying DB: ',  $e->getMessage(), "\n";
    }
  }

  public function setLinkPageType($url, $pagetype='unknown') {
    // Prepare SQL Update Statement for setting link to 'not included'
    $stmt = $this->pdo->prepare('UPDATE links SET type = :pagetype, worked = 1 where url = :url');
    try {
      // execute sql update statement
      $stmt->execute([
                  ':url' => $url,
                  ':pagetype' => $pagetype
                  ]);
    } catch (Exception $e) {
      echo 'Error writing to DB: ',  $e->getMessage(), "\n";
    }
  }

  public function insertCityReject($url) {
    // prepare sql statement
    $sql = 'INSERT INTO city_rejects(url) VALUES(:url)';
    $stmt = $this->pdo->prepare($sql);
    try {
      // execute sql insert statement
      $stmt->execute([':url' => $url]);
    } catch (Exception $e) {
      echo 'Error writing to DB: ',  $e->getMessage(), "\n";
    }
  }

  public function retrieveCityRejects() {
    // prepare select statement
    $stmt = $this->pdo->query('SELECT id, url FROM city_rejects');
    // create empty $citydata object
    $cityrejects = [];
    // fetch data from statement
    try {
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        // update with rows of data
        $cityrejects[] = [
          'id' => $row['id'],
          'url' => $row['url']
        ];
      }
    } catch (Exception $e) {
      echo 'Error retrieving data: ',  $e->getMessage(), "\n";
    }
    return $cityrejects;
  }

  public function insertRobotPages() {
    // use curl to get robots.txt info
    $res = (new CurlDataRetrieval())->getPageData('https://www.musement.com/robots.txt');
    // build array containing disallowed pages
    $robarr = explode("Disallow: /*", str_replace("\n", "", $res['content']));
    // remove the other text from robots.txt from array
    array_shift($robarr);

    foreach ($robarr as $url) {
      // prepare sql statement
      $sql = 'INSERT INTO robot_pages(url) VALUES(:url)';
      $stmt = $this->pdo->prepare($sql);
      try {
        // execute sql insert statement
        $stmt->execute([':url' => $url]);
      } catch (Exception $e) {
        echo 'Error writing to DB: ',  $e->getMessage(), "\n";
      }
    }
  }

  public function retrieveRobotPages() {
    // prepare select statement
    $stmt = $this->pdo->query('SELECT id, url FROM robot_pages');
    // create empty $citydata object
    $robotpages = [];
    // fetch data from statement
    try {
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        // update with rows of data
        $robotpages[] = [
          'id' => $row['id'],
          'url' => $row['url']
        ];
      }
    } catch (Exception $e) {
      echo 'Error retrieving data: ',  $e->getMessage(), "\n";
    }
    return $robotpages;
  }


}
?>
