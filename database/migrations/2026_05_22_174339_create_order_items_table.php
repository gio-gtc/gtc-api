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
            
            // Financial Lock Rule
            $table->decimal('price_locked', 10, 2); 
            
            $table->string('status')->default('New Order');
            $table->date('due_date')->nullable();
            $table->string('asset_url')->nullable();
            $table->string('mime_type')->nullable();
            
            // Dynamic field bucket (holds Encoding, ISCI, Card Holders, Language, etc.)
            $table->json('specifications')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};