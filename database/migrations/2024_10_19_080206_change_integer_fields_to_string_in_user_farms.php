<?php

// database/migrations/xxxx_xx_xx_change_integer_fields_to_string_in_user_farms.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ChangeIntegerFieldsToStringInUserFarms extends Migration
{
    public function up()
    {
        Schema::table('user_farms', function (Blueprint $table) {
            $table->string('irrigation_source')->nullable()->change();
            $table->string('soil_type')->nullable()->change();
            $table->string('sowing_method')->nullable()->change();
            $table->string('seed_variety')->nullable()->change();
            $table->string('crop')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('user_farms', function (Blueprint $table) {
            $table->integer('irrigation_source')->nullable()->change();
            $table->integer('soil_type')->nullable()->change();
            $table->integer('sowing_method')->nullable()->change();
            $table->integer('seed_variety')->nullable()->change();
            $table->integer('crop')->nullable()->change();
        });
    }
}
