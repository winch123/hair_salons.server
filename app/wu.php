<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

//use DB;

function query($sql, $params=[]) {
    $connection = DB::connection('mysql2');
    //$connection = DB::connection('mysql3');

    $tables = [
        '{firms}' => env('DB_DATABASE3') . '.firms',
        '{users}' => env('DB_DATABASE') . '.users',
    ];
    $sql = str_replace(array_keys($tables), $tables, $sql);

    list($sql, $params) = replaceParams($sql, $params);

    switch (strtoupper(substr(trim($sql), 0, 6))) {
        case 'SELECT':
            return $connection->select($sql, $params);
	case 'INSERT':
	    $connection->insert($sql, $params);
	    return $connection->getPdo()->lastInsertId();
	case 'UPDATE':
	    return $connection->update($sql, $params);
	    case 'DELETE':
            return $connection->delete($sql, $params);
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

/*
function w_getEnv($env_name) {  // костыльная функция для получения настроек из .env
    $handle = @fopen(__DIR__.'/../.env', 'r');

    if ($handle) {
        while (($buffer = fgets($handle, 4096)) !== false) {
            @list($name, $val) = explode('=', $buffer);
            if ($name == $env_name) {
                fclose($handle);
                return trim($val);
            }
        }
        if (!feof($handle)) {
            throw new Exception('Ошибка: fgets() неожиданно потерпел неудачу');

        }
        fclose($handle);
    }
}
*/

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

function setSetField(int $objId, array $flags, int $act, string $tableName, string $fieldName, string $keyField='id')
{
    if ($act === 1) { //replace
        query("UPDATE $tableName SET $fieldName=? WHERE $keyField=?", [implode(',' ,$flags), $objId]);
    }
    elseif ($act === 2) { //add
        query("UPDATE $tableName SET $fieldName=CONCAT($fieldName, ?) WHERE $keyField=?", [','.implode(',' ,$flags), $objId]);
    }
    elseif ($act === 3) { //remove
        foreach($flags as $flag) {
            query("UPDATE $tableName SET $fieldName=TRIM(BOTH ',' FROM REPLACE(CONCAT(',', $fieldName, ','), ?, ',')) WHERE id=?",
                [','.$flag.',', $objId]);
        }
    }
}

function strToAssoc(string $str): object
{
    // На выходе ассоциированный массив с ключами по наванием ролей и значениями true.
    // Его удобно проверять, т.к. нет необходимости "бегать" по массиву.
    return (object) array_fill_keys(array_diff(explode(',',  $str), ['']), true);
}

function genpass($len, $param=1){
   $arr = array('a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t','u','v','w','x','y','z',
	'1','2','3','4','5','6','7','8','9','0',
        'A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z',
        '.',',','(',')','[',']','!','?','&amp;','^','%','@','*','$','&lt;','&gt;','/','|','+','-','{','}','`','~');
   $pass = "";
   if ($param>count($arr)-1) $param=count($arr) - 1;
   elseif ($param==1) $param=25;
   elseif ($param==2) $param=35;
   elseif ($param==3) $param=61;
   elseif ($param==4) $param=count($arr) - 1;
   for($i = 0; $i < $len; $i++){
      // Вычисляем случайный индекс массива
      $index = rand(0, $param);
      $pass .= $arr[$index];
   }
   return $pass;
}
