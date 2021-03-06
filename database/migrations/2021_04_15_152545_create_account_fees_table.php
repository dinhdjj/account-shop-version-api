<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountFeesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_fees', function (Blueprint $table) {
            $table->id();
            $table->integer('maximum_cost')->nullable();
            $table->integer('minimum_cost')->nullable();
            $table->integer('maximum_fee')->nullable();
            $table->integer('minimum_fee')->nullable();
            $table->integer('percentage_cost')->default(0);
            $table->integer('direct_fee')->default(0);


            $table->integer('account_type_id');
            $table->unsignedBigInteger('latest_updater_id')->nullable();
            $table->unsignedBigInteger('creator_id')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_fees');
    }
}
