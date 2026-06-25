<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Accounting Invoice Architecture
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('organisation_id')->constrained(); 
            $table->string('document_number')->unique();
            $table->string('status'); // Held, Unpaid, Sent, Paid
            $table->decimal('subtotal', 10, 2);
            $table->decimal('tax', 10, 2)->default(0.00);
            $table->decimal('total', 10, 2);
            $table->timestamp('payment_due')->nullable(); // Kept nullable for Held logs
            $table->timestamps();
        });

        // 2. Snapshot Invoiced Items Mapping
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->string('description');
            $table->decimal('unit_price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->decimal('total', 10, 2);
            $table->timestamps();
        });

        // 3. Document Number Sequencing Locking Table
        Schema::create('invoice_document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('sequence_key')->unique();
            $table->unsignedBigInteger('last_value')->default(975949); 
            $table->timestamps();
        });
    }

    public function down(): void
    { 
        // Dropped in reverse dependency sequence order to prevent foreign key constraint breaks
        Schema::dropIfExists('invoice_document_sequences');
        Schema::dropIfExists('invoice_lines');
        Schema::dropIfExists('invoices');
    }
};