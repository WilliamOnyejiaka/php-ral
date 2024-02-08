<?php

namespace Lib;

class Pagination
{

  protected $connection;
  protected $page;
  protected $results_per_page;
  protected $needed_attributes;
  protected string $sql;
  protected $sqlBindParams;

  public function __construct($connection, string $sql, $needed_attributes, $params, array $sqlBindParams = null)
  {
    $this->connection = $connection;
    $this->needed_attributes = $needed_attributes;
    $this->page = !empty($params['page']) && $params['page'] >= 1 ? $params['page'] : 1;
    $this->results_per_page = !empty($params['results_per_page']) && $params['results_per_page'] >= 1 ? $params['results_per_page'] : 10;
    $this->sql = $sql;
    $this->sqlBindParams = !empty($sqlBindParams) ? $sqlBindParams : [];
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

  protected static function getTypes(array $params): string
  {
    $types = '';
    foreach ($params as $param) {
      $types .= self::getParamType($param);
    }

    return $types;
  }

  protected function executeQuery(string $sql)
  {
    if (!empty($this->sqlBindParams)) {
      $stmt = $this->connection->prepare($sql);
      $paramTypes = self::getTypes($this->sqlBindParams);
      $stmt->bind_param($paramTypes, ...$this->sqlBindParams);
      $stmt->execute();
      return $stmt->get_result();
    }

    return $this->connection->query($sql);
  }

  private function get_data()
  {
    return $this->executeQuery($this->sql);
  }

  protected function tbl_row_length()
  {
    return $this->get_data()->num_rows;
  }

  protected function get_page_results()
  {
    $page_results = ($this->page - 1) * $this->results_per_page;
    return $page_results;
  }

  protected function get_number_of_results()
  {
    return $this->tbl_row_length();
  }

  protected function get_number_of_pages()
  {
    $number_of_results = $this->tbl_row_length();
    $number_of_pages = ceil($number_of_results / $this->results_per_page);
    return $number_of_pages;
  }

  private function get_page_data()
  {
    $page_results = $this->get_page_results();
    $stmt = null;
    $result = null;
    $data = array();
    if (!empty($this->sqlBindParams)) {
      $query = "$this->sql LIMIT $page_results, $this->results_per_page";
      $stmt = $this->connection->prepare($query);
      $paramTypes = self::getTypes($this->sqlBindParams);
      $stmt->bind_param($paramTypes, ...$this->sqlBindParams);
      $stmt->execute();
      $result = $stmt->get_result();
    } else {
      $query = "$this->sql LIMIT $page_results, $this->results_per_page";
      $result = $this->connection->query($query);
    }


    while ($row = $result->fetch_assoc()) {
      $entity = array();
      foreach ($this->needed_attributes as $item) {
        $entity[$item] = $row[$item];
      }
      array_push($data, $entity);
    }
    return $data;
  }

  protected function create_page_properties()
  {
    [$next_page, $prev_page, $has_next, $has_prev] = [null, null, false, false];

    if ($this->page <= 1 && $this->get_number_of_pages() <= 1) {
      [$next_page, $prev_page, $has_next, $has_prev] = [null, null, false, false];
    } else if ($this->page <= 1 && $this->get_number_of_pages() > 1) {
      $next_page = $this->page + 1;
      $has_next = true;
    } else if ($this->page == $this->get_number_of_pages()) {
      $prev_page = $this->page - 1;
      $has_prev = true;
    } else if ($this->page > $this->get_number_of_pages()) {
      [$next_page, $prev_page, $has_next, $has_prev] = [null, null, false, false];
    } else {
      $next_page = $this->page + 1;
      $prev_page = $this->page - 1;
      $has_next = true;
      $has_prev = true;
    }

    return [$next_page, $prev_page, $has_next, $has_prev];
  }

  public function meta_data()
  {
    $page_properties = $this->create_page_properties();
    return array(
      'data' => $this->get_page_data(),
      'currentPage' => $this->page,
      'nextPage' => $page_properties[0],
      'prevPage' => $page_properties[1],
      'hasNext' => $page_properties[2],
      'hasPrev' => $page_properties[3],
      'totalResults' => $this->get_number_of_results(),
      'numberOfPages' => $this->get_number_of_pages()
    );
  }
}
