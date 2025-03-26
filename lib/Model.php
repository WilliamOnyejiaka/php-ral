<?php

declare(strict_types=1);

namespace Lib;

ini_set('display_errors', 1);

use Lib\Database;
use Lib\Response;

class Model
{

    public $connection;
    protected string $tblName;
    protected Response $response;
    protected string $createQuery;

    public function __construct(string $host, string $username, string $password, string $dbName, int $port = 3306)
    {
        $this->connection = (new Database($host, $username, $password, $dbName, $port))->connect();
        $this->response = new Response();
    }

    protected function executionError($executed)
    {
        if (!$executed) {
            $this->response->json([
                'error' => true,
                'message' => "something went wrong"
            ],500);
            exit();
        }
    }

    public function createTbl()
    {
        return $this->connection->query($this->createQuery) ? true : false;
    }

    public function tableExists()
    {
        $query = "SHOW TABLES LIKE '$this->tblName'";
        $result = $this->connection->query($query);
        return $result->num_rows > 0 ? true : false;
    }

    public function dropTbl()
    {
        $query = "DROP TABLE IF EXISTS $this->tblName";
        return $this->connection->query($query) ? true : false;
    }

    private static function getParamType($param)
    {
        if (is_int($param)) {
            return 'i'; // Integer
        } elseif (is_float($param)) {
            return 'd'; // Double
        } elseif (is_string($param)) {
            return 's'; // String
        } else {
            return 's'; // Default to string if type is unknown
        }
    }

    private static function getTypes(array $params): string
    {
        $types = '';
        foreach ($params as $param) {
            $types .= Model::getParamType($param);
        }

        return $types;
    }

    protected function executeQuery(string $sql, array $params, bool $affectRow = false)
    {
        try {
            $stmt = $this->connection->prepare($sql);
            if ($stmt === false) {
                die("Error in preparing statement: " . $this->connection->error);
            }

            if ($params) {
                $types = Model::getTypes($params);
                $stmt->bind_param($types, ...$params);
            }
            $this->executionError($stmt->execute() ? true : false);
            if ($affectRow) {
                $result = $stmt->affected_rows > 0;
            } else {
                $result = $stmt->get_result();
            }
            $stmt->close();

            return $result;
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    protected function affectRowQuery(string $sql, array $params)
    {
        return $this->executeQuery($sql, $params, true);
    }

    protected function queryWithParams(string $sql, array $params = null)
    {
        return $this->executeQuery($sql, $params);
    }

    public function close(): void
    {
        try {
            $this->connection->close();
        } catch (\Exception $e) {
            die($e->getMessage());
        }
    }

    protected function sanitize(mixed $param)
    {
        return htmlentities(strip_tags($param));
    }
}
