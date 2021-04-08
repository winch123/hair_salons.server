<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class InitHS extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $path = __DIR__.'/hs.sql';
        //var_dump(file_get_contents($path));
        $res = DB::connection('mysql2')->unprepared(file_get_contents($path));
        var_dump($res);
    }
}
