<?php
namespace App\winch;

use DB;

class ImagesStore
{
    static $storePath;
    static $urlPath = '/img/public/';

    function __construct() {
        self::$storePath = $_SERVER['DOCUMENT_ROOT'] . '/../storage/app/public/';
    }

    function saveImage($objId, $objType, $downloadedFile, $subtype=1)
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

        $path =  self::$storePath.'initial/'. $this->getDir($fn);
        if (!file_exists($path)) {
            mkdir($path);
        }
        move_uploaded_file($downloadedFile['tmp_name'], "$path/$fn.$imgType");

        return $fn;
    }

    private function getDir($fn) {
        return substr($fn, 0, 2);
    }

    function removeImage($objId, $objType, $imageFullName)
    {
        list('filename' => $filename) = pathinfo($imageFullName);
        //var_dump([$filename, $objId, $objType]);
        $r = query("SELECT img_type FROM images WHERE file_name=? AND obj_id=? AND obj_type=?", [$filename, $objId, $objType]);
        if (empty($r)) {
            throw new \Exception('неизвестная картинка');
        }

        query("UPDATE images SET status='deleted' WHERE file_name=?", [$filename]);

        // Почистить все кеши.
        foreach(['initial', 'standard', 'preview'] as $ccc) {
            $fn = self::$storePath. $ccc. '/'.$this->getDir($filename).'/'.$filename.'.'.$r[0]->img_type;
            var_dump($fn);
            if (file_exists($fn)) {
                unlink($fn);
            }
        }
    }

    function getImagesOfObjects($objIds, $objType, $subtype=1) {
        $imgs = DB::connection('mysql2')
                ->table('images')
                ->select('file_name', 'img_type', 'obj_id')
                ->whereIn('obj_id', $objIds)
                ->where('obj_type', '=', $objType)
                ->where('subtype', '=', $subtype)
                ->where('status', '=', 'active')
                ->get();
        $res = [];
        foreach($imgs as $img) {
            $dir = $this->getDir($img->file_name);
            $res[$img->obj_id][] = [
                'standard' => self::$urlPath."standard/$dir/$img->file_name.$img->img_type",
                'preview' => self::$urlPath."preview/$dir/$img->file_name.$img->img_type",
            ];
        }
        //var_dump($res);
        return $res;
    }
}
