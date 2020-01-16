<?php
namespace App;

/**
 * SQLite connnection
 */
class SQLiteConnection {
    /**
     * PDO instance
     * @var type
     */
    private $pdo;

    /**
     * return in instance of the PDO object that connects to the SQLite database
     * @return \PDO
     */
    public function connect($init=0) {
        if ($init=1) {
          unlink(Config::PATH_TO_SQLITE_FILE);
        }
        if ($this->pdo == null) {
          try {
             $this->pdo = new \PDO("sqlite:" . Config::PATH_TO_SQLITE_FILE);
          } catch (\PDOException $e) {
             throw new Exception('Unable to connect to database.');
          }
        }
        return $this->pdo;
    }
}
?>
