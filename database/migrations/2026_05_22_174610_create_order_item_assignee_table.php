<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_assignee', function (Blueprint $table) {
            $table->id();
            
            // Link to the specific line item
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            
            // Link to the user table (the team member acting as the assignee)
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            
            $table->timestamps();

            // Prevent duplicate assignments of the same user to the same item
            $table->unique(['order_item_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_assignee');
    }
};