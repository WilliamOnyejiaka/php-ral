<?php

namespace Lib;

ini_set('display_errors', 1);

class Database
{

  private $host;
  private $dbName;
  private $username;
  private $password;
  private $port;

  public function __construct(string $host, string $username, string $password, string $dbName, int $port = 3306)
  {
    $this->host = $host;
    $this->username = $username;
    $this->password = $password;
    $this->dbName = $dbName;
    $this->port = $port;
  }

  public function connect()
  {
    $conn = new \mysqli($this->host, $this->username, $this->password, $this->dbName, $this->port);
    if ($conn->connect_errno) {
      print_r($conn->error);
      exit;
    } else {
      return $conn;
    }
  }
}
