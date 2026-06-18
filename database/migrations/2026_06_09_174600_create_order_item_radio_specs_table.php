<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('order_item_radio_specs', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('cut'); 
            $table->string('duration_seconds');
            $table->string('language');
            $table->string('isci')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_item_radio_specs');
    }
};
