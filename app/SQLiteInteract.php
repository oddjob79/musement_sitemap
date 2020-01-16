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

  // takes $links multi-dimensional array and uses data to update the links table
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

  // TO DO - Refactor as update Links
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

  // refactor to use an array to populate with multiple reject urls (foreach)
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
