<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_broadcast_specs', function (Blueprint $table) {
            $table->id();
            $table->string('type')->nullable();
            $table->string('cut')->nullable();
            $table->string('duration_seconds')->nullable(); 
            $table->string('language')->nullable();
            $table->json('encoding')->nullable(); 
            $table->string('isci')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_broadcast_specs');
    }
};