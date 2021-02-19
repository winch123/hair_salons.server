<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

//use DB;

function query($sql, $params=[]) {
    //$connection = DB::connection('mysql2');
    $connection = DB::connection('mysql3');

    list($sql, $params) = replaceParams($sql, $params);

    switch (strtoupper(substr(trim($sql), 0, 6))) {
        case 'SELECT':
            return $connection->select($sql, $params);
	case 'INSERT':
	    $connection->insert($sql, $params);
	    return $connection->getPdo()->lastInsertId();
	case 'UPDATE':
	    return $connection->update($sql, $params);
        default:
            throw new UnexpectedValueException("не известный тип запроса:\n $sql");
    }
}

function replaceParams($sql, $params) {
	$i = 0;
	$p = array();
	foreach ($params as $k=>$v) {
	if (is_array($v)){
		$sql = str_replace('ph'.$i++, implode(',', array_fill(0, count($v), '?')), $sql);
		foreach ($v as $v0)
		$p[] = $v0;
	}
	else
		$p[$k] = $v;
	}
	return array($sql, $p);
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
    //$fn = '/tmp/test1';
    $fn = '/home/winch/1/test1';
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

function setExtra(int $objId, array $attribs, string $tableName, string $attributesField='extra', string $keyField='id')
{
    if ( sizeof(array_merge($attribs, $attribs)) != sizeof($attribs) ) { // проверка ассоативности входного массива.
        throw new Exception('non associative array', 0);
    }
    $a = query("SELECT $attributesField FROM $tableName WHERE $keyField=? ", [$objId]);
    if (sizeof($a) == 0) {
        throw new OutOfBoundsException("in $tableName not found $objId");
    }
    $old_a = json_decode($a[0]->$attributesField, true) ?: array();

    $new_a = array_diff(array_merge($old_a, $attribs), [null]);  // объединяем и отбрасываем пустые элементы
    //var_dump($new_a);
    query("UPDATE $tableName SET $attributesField=? WHERE $keyField=?", [json_encode($new_a, JSON_UNESCAPED_UNICODE), $objId]);
}
