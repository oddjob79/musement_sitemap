<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\CurlDataRetrieval as CurlDataRetrieval;


/**
 * SQLite Interact with Database
 */
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

  /**
   * create tables
   */
  public function createTables() {
      $commands = ['CREATE TABLE IF NOT EXISTS cities (
                      id TINYINT PRIMARY KEY,
                      name VARCHAR(50) NOT NULL,
                      url VARCHAR(100) NOT NULL
                    )',
          'CREATE TABLE IF NOT EXISTS events (
                  id INTEGER PRIMARY KEY AUTOINCREMENT,
                  uuid VARCHAR(36) NOT NULL,
                  title VARCHAR(255) NOT NULL,
                  url VARCHAR(255) NOT NULL,
                  city_id TINYINT NOT NULL)'];
      // execute the sql commands to create new tables
      foreach ($commands as $command) {
          $this->pdo->exec($command);
      }
  }

  // Inserts city id and url into cities table
  // @param int $id
  // @param string $url
  public function insertCities() {
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
  // return array containing city ids & urls
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

  public function insertEvents() {
    // retrieve top 20 cities
    $citydata = $this->retrieveCities();

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

}
?>
