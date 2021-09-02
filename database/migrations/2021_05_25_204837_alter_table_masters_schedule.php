<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterTableMastersSchedule extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $db = DB::connection('mysql2');
        $db->statement("ALTER TABLE `masters_schedule` CHANGE `service_id` `service_id` INT UNSIGNED NULL");
        $db->statement("ALTER TABLE `masters_schedule` ADD FOREIGN KEY masters_schedule_ibfk_2 (`service_id`) REFERENCES `services`(`id`) ON DELETE RESTRICT ON UPDATE RESTRICT");
        $db->statement("ALTER TABLE `services` ADD `for_whom` ENUM('undefined', 'male','female','child') NOT NULL AFTER `parent_service`");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $db = DB::connection('mysql2');
        $db->statement("ALTER TABLE `masters_schedule` CHANGE `service_id` `service_id` INT UNSIGNED NOT NULL");
        $db->statement("ALTER TABLE hs.masters_schedule DROP FOREIGN KEY masters_schedule_ibfk_2");
        $db->statement("ALTER TABLE `services` DROP `for_whom");
    }
}
