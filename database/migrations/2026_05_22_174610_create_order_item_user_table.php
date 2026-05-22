<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_user', function (Blueprint $table) {
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            // Unique index to prevent accidentally assigning the same user twice
            $table->primary(['order_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_user');
    }
};