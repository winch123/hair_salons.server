<?php
namespace App\winch;

//use DB;

class YandexMapsParser
{
    public function process_firms($firms) {
        $dir = '/home/winch/1/';
        $i = 0;
        foreach ($firms as $firm) {
            $fn = $dir . rand(10000, 999999999);
            //var_dump($firm);
            file_put_contents($fn, json_encode($firm));
            try {
                if (isset($firm->categories)) {
                    foreach($firm->categories as $category) {
                        $this->_save_category($category, $firm->id);
                    }
                }
                if (isset($firm->region)) {
                    $this->_save_region($firm->region);
                }
                $this->_save_firm($firm);

                unlink($fn);
                $i++;
            }
            catch (\Exception $e) {
                $err = [
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine(),
                    //$e->getTrace(),
                ];
                //var_dump($err);

                file_put_contents($fn, "\n" . print_r($err, true), FILE_APPEND);
            }

        }
        return $i;
    }

    private function _save_firm($f) {
        var_dump($f->title);
        $r = query("SELECT * FROM firms WHERE id=?", [$f->id]);
        if (empty($r)) {
            query("INSERT INTO firms (id) Values (?)", [$f->id]);
        }

        query("UPDATE firms SET name=?,	region_id=?, locality=?, street=?, house=?, coordinates=ST_GeomFromText(?) WHERE id=?", [
            $f->title,
            isset($f->region) ? $f->region->id : null,
            $f->compositeAddress->locality,
            $f->compositeAddress->street,
            $f->compositeAddress->house,
            "Point({$f->coordinates[0]} {$f->coordinates[1]})",
            $f->id]);

    }

    private function _save_region($reg) {
        $r = query("SELECT * FROM regions WHERE id=?", [$reg->id]);
        if (empty($r)) {
            query("INSERT INTO regions (id, seoname, bounds)
                VALUES (?, ?, ST_GeomFromText(?))",
                [$reg->id, $reg->seoname, "MultiPoint({$reg->bounds[0][0]} {$reg->bounds[0][1]}, {$reg->bounds[1][0]} {$reg->bounds[1][1]})"]);
        }
    }

    private function _save_category($cat, $firmId) {
        $r = query("SELECT * FROM categories WHERE id=?", [$cat->id]);
        if (empty($r)) {
            query("INSERT INTO categories (id, name, class, seoname) VALUES (?, ?, ?, ?)",
                [$cat->id, $cat->name, $cat->class, $cat->seoname]);
        }

        query("INSERT IGNORE INTO firms_categories (firm_id, category_id) VALUES (?,?)", [$firmId, $cat->id]);
    }
}
