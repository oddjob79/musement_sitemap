<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

/**
 * Includes all methods which read data from Database
 */
class SQLiteRead extends SQLiteConnection {

  /**
   * PDO object
   * @var \PDO
   */
  private $pdo;

  /**
   * connect to the SQLite database
   */
   public function __construct() {
       $this->pdo = $this->connect();
   }


  /**
  * Method which returns all data from the cities table. This is the 'top 20' cities, as given from the musement.com API.
  * @return array $citydata - contains the id, name and url columns for each row in the cities database table
  */
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

  /**
  * Method which returns all data from the events table. This is the 'top 20' activities, for the 'top 20 cities', as given from the musement.com API.
  * @return array $eventdata - contains the id, uuid, title, url and city_id columns for each row in the events database table
  */
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

  /**
  * Method which returns all data from the links table
  * @return array $linkdata - contains the id, url, type, include and worked columns for each row in the links database table
  */
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

  /**
  * Method which returns all data from the city_rejects table
  * @return array $cityrejects - contains the id and url columns for each row in the city_rejects database table
  */
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

  /**
  * Method which returns all data from the robot_pages table
  * @return array $robotpages - contains the id and url columns for each row in the robot_pages database table
  */
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

  /**
  * Method which returns a list of ids from the links table, only for rows which are set to include (default) and which have not yet been worked
  * This is used exclusively to check if there are any further links left to scan for the Standard Scan
  * @return array $linkcnt - contains the id column only
  */
  public function checkLinksToWork() {
    // prepare select statement
    $stmt = $this->pdo->query('SELECT id FROM links WHERE include = 1 and worked = 0');
    // create empty $linkcnt object
    $linkcnt = [];
    // fetch data from statement
    try {
      while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
        // update with rows of data
        $linkcnt[] = [
          'id' => $row['id']
        ];
      }
    } catch (Exception $e) {
      echo 'Error retrieving data: ',  $e->getMessage(), "\n";
    }
    return $linkcnt;
  }

}
?>
