<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRulesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rules', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('datatype');
            $table->boolean('required')->default(0);
            $table->boolean('multiple')->default(0);
            $table->integer('min')->nullable();
            $table->integer('minlength')->nullable();
            $table->integer('max')->nullable();
            $table->integer('maxlength')->nullable();
            $table->json('values')->nullable();
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
        Schema::dropIfExists('rules');
    }
}