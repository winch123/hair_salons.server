<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
//require_once  "$_SERVER[DOCUMENT_ROOT]/apiclient/autoload.php";


class ProductsImgsException extends Exception{};

function imgPrepare($file){
  list($w_i, $h_i, $type) = getimagesize($file);
  if (!$w_i || !$h_i) {
    throw new ProductsImgsException("Невозможно получить длину и ширину изображения: $file");
  }

  $types = array('','gif','jpeg','png');
  if (!$ext = $types[$type]) {
    throw new ProductsImgsException('Некорректный формат файла');
  }
  $func = 'imagecreatefrom'.$ext;
  $img = $func($file);

  return array($w_i, $h_i, $type, $img);
}

function crop($file_input, $file_output, $crop='square', $percent=false) {
  list($w_i, $h_i, $type, $img) = imgPrepare($file_input);
  $x_d = $y_d = $x_o = $y_o = 0;
  //var_dump($crop);
  if ($crop == 'square') {
    if ($w_i>$h_i){
      $min = $h_i;
      $x_d = ($w_i-$h_i)/2;
    }
    else{
      $min = $w_i;
      $y_d = ($h_i-$w_i)/2;
    }
    $w_o = $h_o = $min;
  }
  else {
    list($x_o, $y_o, $w_o, $h_o) = $crop;
    if ($percent) {
      $w_o *= $w_i / 100;
      $h_o *= $h_i / 100;
      $x_o *= $w_i / 100;
      $y_o *= $h_i / 100;
    }
    /*
    if ($w_o <= 0)
      $w_o += $w_i;
    $w_o -= $x_o;
    if ($h_o <= 0)
      $h_o += $h_i;
    $h_o -= $y_o;
    */

	if ($x_o + $w_o > $w_i)
		$w_o = $w_i - $x_o; // Если ширина выходного изображения больше исходного (с учётом x_o), то уменьшаем её
	if ($y_o + $h_o > $h_i)
		$h_o = $h_i - $y_o; // Если высота выходного изображения больше исходного (с учётом y_o), то уменьшаем её
  }
  //var_dump($w_o);
  //var_dump($h_o);
  if ($img_o = imagecreatetruecolor($w_o, $h_o)){
    if ($type == 3){
      imageAlphaBlending($img_o, false); // для сохранения прозрачности
      imageSaveAlpha($img_o, true);
    }
    imagecopy($img_o, $img, -$x_d, -$y_d, $x_o, $y_o, $w_o+$x_d, $h_o+$y_d);
    SaveImageToFile($img_o, $file_output);
  }
  else{
    throw new ProductsImgsException("проблема создания изображения с размерами: w_o=$w_o, h_o=$h_o ");
  }
}


function resize1($file_input, $file_output, $w_o, $h_o, $percent = false) {
  list($w_i, $h_i, $type, $ext, $func, $img) = imgPrepare($file_input);
  if ($percent) {
    $w_o *= $w_i / 100;
    $h_o *= $h_i / 100;
  }
  if (!$h_o)
    $h_o = $w_o/($w_i/$h_i);
  if (!$w_o)
    $w_o = $h_o/($h_i/$w_i);

  $img_o = imagecreatetruecolor($w_o, $h_o);
  imagecopyresampled($img_o, $img, 0, 0, 0, 0, $w_o, $h_o, $w_i, $h_i);
  if ($type == 2) {
    return imagejpeg($img_o,$file_output, 88);
  }
  else {
    $func = 'image'.$ext;
    return $func($img_o,$file_output);
  }
}


function CalculateSize($Width, $Height, $MaxWidth, $MaxHeight, $MayZoom){
    $coeffH = empty($MaxHeight) ? $Height : $MaxHeight/$Height;
    $coeffW = empty($MaxWidth) ? $Width : $MaxWidth/$Width;
    // var_dump( compact('Width', 'Height', 'MaxWidth', 'MaxHeight', 'coeffH', 'coeffW') );

    $coeff = $coeffW>$coeffH ? $coeffH : $coeffW;
    if ($coeff>1 && !$MayZoom)
    	$coeff = 1;
    return array($Width*$coeff,$Height*$coeff);
}

function resize2($srcname, $dstname, $MaxWidth=0, $MaxHeight=720, $MayZoom=false){
    if (!file_exists($srcname)){
      	throw new ProductsImgsException("file not exists: $srcname");
    }
    list($width,$height,$type) = getimagesize($srcname);
    //var_dump(compact('width', 'height', 'type') );
    if ($width==0 || $height==0)
      return false;
    if ($width/3 > $height) //ограничение максимальной панорамности
      $width = $height * 3;

    list($newWidth,$newHeight) = CalculateSize($width, $height, $MaxWidth, $MaxHeight, $MayZoom);
    //var_dump(compact('type', 'width', 'height', 'MaxWidth', 'MaxHeight','newWidth','newHeight') );
    //die;

    $new_img = imageCreateTrueColor($newWidth,$newHeight);

    if ($type == 2){
      $src = imagecreatefromjpeg($srcname);
    }
    elseif ($type == 3){
      $src = imagecreatefrompng($srcname);
      imageAlphaBlending($new_img, false); // для сохранения прозрачности
      imageSaveAlpha($new_img, true);
    }
    else
      return false;

    imageCopyResampled($new_img, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

    SaveImageToFile($new_img, $dstname);

    imagedestroy($src);
    imagedestroy($new_img);
    return ['width'=>$newWidth, 'height'=>$newHeight];
}

function SaveImageToFile($img, $fname){
    $ext = pathinfo($fname)['extension'];
    if ($ext == 'jpg'){
      if (!imagejpeg($img, $fname, 88))
        throw new ProductsImgsException("ошибка сохранения jpg: $fname");
    }
    elseif ($ext == 'png'){
      //imagecolortransparent($img, imagecolorat($img, 0, 0)); // установка прозрачности по пикселу в левом верхнем углу.
      if (!imagepng($img, $fname))
        throw new ProductsImgsException("ошибка сохранения png: $fname");
    }
    else{
    	throw new ProductsImgsException('непонятный тип выходнго файла');
    }
}

$ImgTypes = array(
	'preview' => array('method' => function($source, $result){
		  if ($newSize = resize2($source, $result, 333, 333, false)){
        var_dump($newSize);
        //throw new Exception('stop');
        $height = $newSize['width'] * 0.4;
        //crop($result, $result, [0, ($newSize['height'] - $height) / 2 , $newSize['width'], $height ]);
      }
	}),

  'standard' => array('method' => function($source, $result){
    	resize2($source, $result, 1024, 1024, false);
  }),

  'category_to_width' => array('method' => function($source, $result){
		  if ($newSize = resize2($source, $result, 720, null, false)){
        var_dump($newSize);
        //throw new Exception('stop');
        $height = $newSize['width'] * 0.4;
        crop($result, $result, [0, 0, $newSize['width'], $height ]);
      }
  }),

  'category_to_quad' => array('method' => function($source, $result){
      if ($newSize = resize2($source, $result, null, 300)){
        var_dump($newSize);
        crop($result, $result, 'square');
      }
  }),

);

echo '<pre>';

var_dump($_GET);

$imgType = explode('/', $_GET['query'])[1];

$file_name  = substr($_GET['query'], strlen($imgType)-1);

$PathToOriginal = str_replace($imgType, 'initial', $_GET['query']);

$source_name = "$_SERVER[DOCUMENT_ROOT]/img/$PathToOriginal";

print_r(compact('file_name', 'PathToOriginal', 'imgType', 'source_name'));

echo '</pre>';
//die;

if (is_callable($ImgTypes[$imgType]['method'])){
  $result_name = __DIR__."/public/$file_name";
  var_dump($result_name);
  if (!file_exists(dirname($result_name))){
    mkdir(dirname($result_name), 0755, true);
  }

	try{
		$ImgTypes[$imgType]['method']($source_name, $result_name);
		header('location: '. $_SERVER['REQUEST_URI'] );
	}
	catch(ProductsImgsException $e){
		echo '<pre>'. $e->getMessage() ."\n" . $e->getLine() ."\n". $e->getFile(). "</pre>";
    header('HTTP/1.0 404 Not Found');
	}
}
else{
  echo "unknown type: $imgType";
	header('HTTP/1.0 404 Not Found');
}
