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

function _gField(Iterable $db_array, string $field_name, bool $unset_original=true): array
{
    $res = array();
    foreach ($db_array as $row) {
      if (!isset($row->$field_name)) {
        throw new Exception("_GatherField: поле $field_name не существует");
      }
      $k = $row->$field_name;
      if ($unset_original) {
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

function mylog($d){
    $fn = '/tmp/test1';
    $deb = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);

    if(is_array($d) || is_object($d)) {
	    $cont = print_r($d, true) . PHP_EOL;
    }
    else {
	    $cont = $d.PHP_EOL;
    }

    $str = date("d.m.Y H:i:s \n");
    // $str .= $deb[1]['file'] .', '. $deb[1]['line'] .PHP_EOL;
    $str .= $deb[0]['file'] .', '. $deb[0]['line'] .PHP_EOL.  $cont;
    file_put_contents($fn, $str, FILE_APPEND);
}
