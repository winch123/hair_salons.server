<?php
namespace App\winch;

use DB;

class ImagesStore
{
    static $storePath;
    static $urlPath = '/img/public/';

    function __construct() {
        self::$storePath = $_SERVER['DOCUMENT_ROOT'] . '/../storage/app/public/initial/';
    }

    function saveImage($downloadedFile, $objId, $objType, $subtype=1)
    {
        do {
            //$fn = genpass($genpass['len'], $genpass['mode']);
            $fn = 'a'.rand(1, 100000);

            $f = query("SELECT * FROM images WHERE file_name=?", [$fn]);
        } while (!empty($f));

        $imgType = [
            IMAGETYPE_JPEG => 'jpg',
            IMAGETYPE_PNG => 'png',
        ][exif_imagetype($downloadedFile['tmp_name'])];
        if (!$imgType) {
            throw new UnexpectedValueException('тип файла не поддерживается');
        }

        query("INSERT INTO images (obj_id, obj_type, subtype, file_name, img_type, original_file_name) VALUES (?,?,?,?,?,?)",
                [$objId, $objType, $subtype, $fn, $imgType, $downloadedFile['name'] ]);

        // TODO: преобразование размера, если превышает максимальные.

        $path =  self::$storePath.'/'.substr($fn, 0, 2);
        if (!file_exists($path)) {
            mkdir($path);
        }
        move_uploaded_file($downloadedFile['tmp_name'], "$path/$fn.$imgType");

        return $fn;
    }

    function getImagesOfObjects($objIds, $objType, $subtype=1) {
        $imgs = DB::connection('mysql2')
                ->table('images')
                ->select('file_name', 'img_type', 'obj_id')
                ->whereIn('obj_id', $objIds)
                ->where('obj_type', '=', $objType)
                ->where('subtype', '=', $subtype)
                ->get();
        $res = [];
        foreach($imgs as $img) {
            $dir = substr($img->file_name, 0, 2);
            $res[$img->obj_id][] = [
                'standard' => self::$urlPath."standard/$dir/$img->file_name.$img->img_type",
                'preview' => self::$urlPath."preview/$dir/$img->file_name.$img->img_type",
            ];
        }
        //var_dump($res);
        return $res;
    }
}
