<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

/**
 * SQLite Write Data to Database
 */
class SQLiteWrite extends SQLiteConnection {

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

  // takes $links multi-dimensional array and uses data to update the links table
  public function insertLinks($links) {
    foreach ($links as $link) {
      // set variables
      $url = $link['url'];
      $type = $link['type'];
      $include = $link['include'];

      // prepare sql statement
      $sql = 'INSERT INTO links(url, type, include) VALUES(:url, :type, :include)';
      $stmt = $this->pdo->prepare($sql);
      try {
        // execute sql insert statement
        $stmt->execute([
          ':url' => $url,
          ':type' => $type,
          ':include' => $include
        ]);
      } catch (Exception $e) {
        echo 'Error writing to DB: ',  $e->getMessage(), "\n";
      }
    }
  }

  public function updateLink($url, $include) {

    error_log('Updating Link', 0);
    // Prepare SQL Update Statement for setting link to 'not included'
    $stmt = $this->pdo->prepare('UPDATE links SET include = :include, worked = 1 where url = :url');

    // passing values to the parameters
    $stmt->bindValue(':include', $include);
    $stmt->bindValue(':url', $url);

    // try to execute sql update statement
    try {
      $stmt->execute();
    } catch (Exception $e) {
      echo 'Error updating DB: ',  $e->getMessage(), "\n";
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

}
?>
