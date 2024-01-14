<?php

namespace Lib;

ini_set('display_errors', 1);

class Database
{

  private $hostname;
  private $db_name;
  private $username;
  private $password;
  private $port;

  public function __construct(string $hostname, string $username, string $password, string $db_name, int $port=3306)
  {
    $this->hostname = $hostname;
    $this->username = $username;
    $this->password = $password;
    $this->db_name = $db_name;
    $this->port = $port;
  }

  public function connect()
  {
    $conn = new \mysqli($this->hostname, $this->username, $this->password, $this->db_name,$this->port);
    if ($conn->connect_errno) {
      print_r($conn->error);
      exit;
    } else {
      return $conn;
    }
  }
}
