<?php
// enable use of namespaces
require 'vendor/autoload.php';

// use classes for SQLite connection
use App\SQLiteConnection as SQLiteConnection;
use App\SQLiteInteract as SQLiteInteract;

// Create and Populate database and tables, if needed
function createPopulateDB() {
  // connect to sqlite db, or create if not exists
  $pdo = (new SQLiteConnection())->connect();
  // instantiate the SQLiteInteract class
  $sqlite = new SQLiteInteract($pdo);
  // use createTables method to create the db tables, if they don't already exist
  $sqlite->createTables();
  $sqlite->insertCities();
  $sqlite->insertEvents();
}

createPopulateDB();
// populateDB();

?>
