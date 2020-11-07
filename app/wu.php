<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

//use DB;

function query($sql, $params=[]) {
    switch (strtoupper(substr(trim($sql), 0, 6))) {
        case 'SELECT':
            return DB::connection('mysql2')->select($sql, $params);
        default:
            throw new ApiException("не известный тип запроса");
    }
}

function _gField(array $db_array, $field_name, $unset_original=true){
    $res = array();
    foreach ($db_array as $row){
      if (!isset($row->$field_name)) {
        throw new ApiException("_GatherField: поле $field_name не существует");
      }
      $k = $row->$field_name;
      if ($unset_original){
        unset($row->$field_name);
        //var_dump($row);
      }
      if(true)
        $res[ $k ] = (array)$row ;
      else
        $res[ $k ][] = $row ;
    }
    return $res;
}

// конвертация 11:30 в 690 (минуты с начала суток)
function timeToMinutes($time_str) {
    list($h, $m) = explode(':', $time_str);
    return (int)$h * 60 + (int)$m;
}
