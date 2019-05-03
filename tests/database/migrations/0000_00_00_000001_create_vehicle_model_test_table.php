<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVehicleModelTestTable extends Migration
{
    //connection name
    public $connection = 'testing';

    //table
    public $table = 'vehicles';

    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::connection($this->getConnection())->create($this->table, function (Blueprint $table) {
            $table->increments('vehicle_id');
            $table->string('name', 50)->unique();
            $table->double('height')->nullable();
            $table->double('fuel_consumption_city')->nullable();
            $table->text('height_ucd');
            $table->text('fuel_consumption_city_ucd');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::connection($this->getConnection())->drop($this->table);
    }
}
