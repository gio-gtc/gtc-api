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
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['order_item_id', 'user_id']);
        });

        // Accounting Invoice Architecture
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('organisation_id')->constrained(); 
            $table->string('document_number')->unique();
            $table->string('status'); // e.g., Held, Unpaid, Paid
            $table->integer('subtotal_cents');
            $table->integer('tax_cents')->default(0);
            $table->integer('total_cents');
            $table->timestamp('payment_due')->nullable();
            $table->timestamps();
        });

        // Snapshot Invoiced Items Mapping
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->nullable()->constrained()->onDelete('set null'); 
            $table->string('description');
            $table->integer('unit_price_cents');
            $table->integer('quantity')->default(1);
            $table->integer('total_cents');
            $table->timestamps();
        });

        // Document Number Sequencing Locking Table
        Schema::create('invoice_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key')->unique();
            $table->unsignedBigInteger('last_value')->default(975949); 
            $table->timestamps();
        });
    }

    public function down(): void
    { 
        Schema::dropIfExists('invoice_document_sequences');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('order_item_assignee');
    }
};