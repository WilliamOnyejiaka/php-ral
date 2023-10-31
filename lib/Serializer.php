<?php

namespace Lib;

class Serializer
{

  public static function tuple($result, array $needed_attributes)
  {
    if ($result->num_rows == 0) {
      return [];
    } else {
      $data = [];
      while ($row = $result->fetch_assoc()) {
        foreach ($needed_attributes as $attr) {
          $data[$attr] = $row[$attr];
        }
      }
      return $data;
    }
  }

  public static function dump_all($result, array $needed_attributes)
  {
    if ($result->num_rows == 0) {
      return [];
    } else {
      $data = [];
      while ($row = $result->fetch_assoc()) {
        $values = [];
        foreach ($needed_attributes as $attr) {
          $values[$attr] = $row[$attr];
        }

        array_push($data, $values);
      }
      return $data;
    }
  }
}