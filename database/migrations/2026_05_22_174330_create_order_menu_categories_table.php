<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_menu_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., Audio, Social Video
            $table->json('required_tags')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_menu_categories');
    }
};