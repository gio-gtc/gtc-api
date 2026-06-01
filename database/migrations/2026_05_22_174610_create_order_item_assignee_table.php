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
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->integer('company_id')->default(1);
            $table->integer('document_number'); // Sequential plain integer billing tracker
            $table->string('status')->default('Held'); // Title Case State Standard
            $table->date('payment_due');
            $table->timestamps();
        });

        // Snapshot Invoiced Items Mapping
        Schema::create('invoice_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained('order_items')->nullOnDelete();
            $table->string('description'); // Allows admin descriptions overrides
            $table->decimal('price', 10, 2); // Allows admin pricing adjustments
            $table->timestamps();
        });

        // Document Number Sequencing Locking Table
        Schema::create('invoice_document_sequences', function (Blueprint $table) {
            $table->integer('company_id')->primary(); // Shared global mapping base
            $table->integer('last_document_number')->default(0);
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