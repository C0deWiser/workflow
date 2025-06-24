<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransitionHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('transition_history', function (Blueprint $table) {
            $table->id();
            $table->nullableMorphs('performer');
            $table->morphs('transitionable');
            $table->string('blueprint');
            $table->string('source')->nullable();
            $table->string('target');
            $table->text('context')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('transition_history');
    }
}
