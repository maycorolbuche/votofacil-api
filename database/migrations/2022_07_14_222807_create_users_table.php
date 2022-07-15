<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id_user');
            $table->unsignedInteger('id_room');
            $table->unsignedInteger('id_device');
            $table->string('name',250);
            $table->enum('type',['user', 'preview']);
            $table->timestamps();

            $table->foreign('id_room')->references('id_room')->on('rooms');
            $table->foreign('id_device')->references('id_device')->on('devices');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
}
