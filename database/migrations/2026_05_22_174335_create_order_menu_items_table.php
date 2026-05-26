<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_menu_category_id')->constrained('order_menu_categories')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('default_price', 10, 2);
            
            $table->json('form_blueprint')->nullable(); 
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_menu_items');
    }
};