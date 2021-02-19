<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AlterUsers1 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement("ALTER TABLE `users` ADD `person_id` INT UNSIGNED NULL");
        DB::statement("ALTER TABLE `users` ADD `extra` JSON NULL");
        DB::statement("ALTER TABLE `users` ADD `auth_type` ENUM('phone','email','vk','mail.ru','yandex','google') NOT NULL");
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        DB::statement("ALTER TABLE `users` DROP `person_id`");
        DB::statement("ALTER TABLE `users` DROP `extra`");
        DB::statement("ALTER TABLE `users` DROP `auth_type`");
    }
}
