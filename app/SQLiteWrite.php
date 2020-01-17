<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

use App\CurlDataRetrieval as CurlDataRetrieval;

/**
 * SQLite Write Data to Database
 */
class SQLiteWrite {

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

  // TO DO - Refactor as UPDATE Links
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

}
?>
