<?php

namespace Lib;

use Lib\Pagination;

class SearchPagination extends Pagination
{

  private $keyword;
  private $search_params;

  public function __construct($connection, string $sql, $needed_attributes, mixed $keyword, mixed $search_params, $params, array $sqlBindParams = null)
  {
    parent::__construct($connection, $sql, $needed_attributes, $params, $sqlBindParams);
    $this->connection = $connection;
    $this->sql = $sql;
    $this->needed_attributes = $needed_attributes;
    $this->page = !empty($params['page']) && $params['page'] >= 1 ? $params['page'] : 1;
    $this->results_per_page = !empty($params['results_per_page']) && $params['results_per_page'] >= 1 ? $params['results_per_page'] : 10;
    $this->keyword = $keyword;
    $this->search_params = $search_params;
  }

  protected function tbl_row_length()
  {
    return $this->get_data()->num_rows;
  }

  protected function get_number_of_pages()
  {
    $number_of_results = $this->tbl_row_length();
    $number_of_pages = ceil($number_of_results / $this->results_per_page);
    return $number_of_pages;
  }

  private function get_search_string()
  {
    $search_string = "";
    for ($i = 0; $i < count($this->search_params); $i++) {
      $field = $this->search_params[$i];
      if ($i == count($this->search_params) - 1) {
        $search_string .= "$field LIKE '%$this->keyword%'";
      } else {
        $search_string .= "$field LIKE '%$this->keyword%' OR ";
      }
    }
    return $search_string;
  }

  private function get_data()
  {
    $search_string = $this->get_search_string();
    $alteredSql = "$this->sql WHERE ($search_string)";
    if (!empty($this->sqlBindParams)) {
      $alteredSql = "$this->sql AND ($search_string)";
    }
    return $this->executeQuery($alteredSql);
  }

  private function get_page_data()
  {
    $page_results = $this->get_page_results();
    $search_string = $this->get_search_string();
    $alteredSql = "$this->sql WHERE ($search_string) LIMIT $page_results, $this->results_per_page";
    if (!empty($this->sqlBindParams)) {
      $alteredSql = "$this->sql AND ($search_string) LIMIT $page_results, $this->results_per_page";
    }
    $result = $this->executeQuery($alteredSql);
    $data = array();

    while ($row = $result->fetch_assoc()) {
      $entity = array();
      foreach ($this->needed_attributes as $item) {
        $entity[$item] = $row[$item];
      }
      array_push($data, $entity);
    }
    return $data;
  }

  public function meta_data()
  {
    $page_properties = $this->create_page_properties();
    return array(
      'data' => $this->get_page_data(),
      'current_page' => $this->page,
      'next_page' => $page_properties[0],
      'prev_page' => $page_properties[1],
      'has_next' => $page_properties[2],
      'has_prev' => $page_properties[3],
      'total_results' => $this->get_number_of_results(),
      'number_of_pages' => $this->get_number_of_pages()
    );
  }
}
