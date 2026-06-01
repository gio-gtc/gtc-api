<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignId('order_menu_item_id')->constrained('order_menu_items')->restrictOnDelete();
            
            // Renamed from price_locked to match system naming rules
            $table->decimal('locked_price', 10, 2); 
            
            $table->string('status')->nullable();
            $table->date('due_date')->nullable();
            $table->string('asset_url')->nullable();
            $table->string('mime_type')->nullable();
            
            // Revision Lineage Tracking Structure
            $table->unsignedBigInteger('root_order_item_id')->nullable();
            $table->integer('revision_number')->default(1);
            $table->unsignedBigInteger('supersedes_order_item_id')->nullable();
            $table->unsignedBigInteger('invoice_line_id')->nullable();
            
            $table->json('specifications')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};