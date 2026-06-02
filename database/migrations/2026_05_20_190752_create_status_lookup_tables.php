<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Master Order Parent Headers Status Table
        Schema::create('order_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'New Order', 'In Progress'
            $table->timestamps();
        });

        // Granular Line Items Status Table
        Schema::create('order_item_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->foreignId('order_status_id')->nullable()->constrained('order_statuses')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_statuses');
        Schema::dropIfExists('order_statuses');
    }
};