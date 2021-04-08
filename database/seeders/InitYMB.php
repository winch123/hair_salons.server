<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class InitYMB extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $path = __DIR__.'/yandex_maps_business.sql';
        DB::connection('mysql3')->unprepared(file_get_contents($path));
    }
}
