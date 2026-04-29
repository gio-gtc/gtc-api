<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            
            $table->string('name')->unique();
            $table->string('code', 2)->unique(); // e.g., US, CA, GB
            
            // Helpful additions for an enterprise billing app
            $table->string('currency_code', 3)->nullable(); // e.g., USD, CAD, EUR
            $table->string('dial_code', 10)->nullable(); // e.g., +1, +44, +353
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};