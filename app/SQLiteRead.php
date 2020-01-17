<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\CurlDataRetrieval as CurlDataRetrieval;

/**
 * SQLite Read Data from Database
 */
class SQLiteRead {

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
