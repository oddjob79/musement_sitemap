<?php

namespace App;

// enable use of namespaces
require 'vendor/autoload.php';

/**
 * Includes all methods which write data to Database (Inserts / Updates)
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

  /**
  * Takes a given array of links, containing the url, type and include flag and inserts the data into the links table
  * @param array $links
  */
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

  /**
  * Takes a given URL, and updates the matching URL in the links table with the given include flag
  * @param string $url - the url to match against the url column in the links table
  * @param int $include
  */
  public function updateLink($url, $include) {
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

  /**
  * Takes a given URL, and inserts it into the city_rejects table.
  * @param string $url
  */
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
