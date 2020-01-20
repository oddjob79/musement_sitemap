<?php

namespace App;

use App\SQLiteRead as SQLiteRead;

// enable use of namespaces
require 'vendor/autoload.php';

/**
 * SQLite Create DB Schema and seed data
 */
class SQLiteDBSetup extends SQLiteConnection {

  /**
   * PDO object
   * @var \PDO
   */
  private $pdo;

  /**
   * connect to the SQLite database
   */
  // Upon calling the DBSetup class, delete old database, create new db and create db schema
  public function __construct() {
      $this->deleteDatabase();
      $this->pdo = $this->connect();
      $this->createTables();
  }

  /**
   * create db schema - cities, events, links, city_rejects, robot_pages
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

  /**
  * Uses the CurlDataRetrieval()->getAPIData method to retrieve the city data for the given locale, then
  * inserts the id, name and the url into the cities table in the database
  * @param string $locale
  */
  private function insertCities($locale) {
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

  /**
  * Reads the top 20 city data from the SQLiteRead->retrieveCities method, then loops through this gathering the top 20
  * activities per city, using the CurlDataRetrieval()->getAPIData for the given locale. Then inserts the uuid, title, url and city_id
  * into the events table in the database
  * @param string $locale
  */
  private function insertEvents($locale) {
    // instantiate the SQLiteDBSetup class
    $sqlread = new SQLiteRead($this->pdo);
    // retrieve top 20 cities
    $citydata = $sqlread->retrieveCities();
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

  /**
  * Uses the CurlDataRetrieval()->getPageData method to retrieve all pages listed in the https://www.musement.com/robots.txt
  * web page, and inserts the url into the robot_pages db table.
  */
  private function insertRobotPages() {
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

  /**
  * Publicly available function to execute the following methods, all needed to seed the database
  * before scanning any web pages: insertCities, insertEvents & insertRobotPages.
  */
  public function seedData($locale) {
    $this->insertCities($locale);
    $this->insertEvents($locale);
    $this->insertRobotPages();
  }

}
?>
