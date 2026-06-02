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
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_menu_item_id')->constrained()->onDelete('cascade');
            $table->unsignedBigInteger('order_item_status_id')->index();
            $table->decimal('locked_price', 10, 2);
            $table->date('due_date')->nullable();
            $table->json('specifications')->nullable();
            $table->unsignedBigInteger('root_order_item_id')->nullable();
            $table->integer('revision_number')->default(1);
            $table->unsignedBigInteger('supersedes_order_item_id')->nullable();
            $table->unsignedBigInteger('invoice_line_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreign('order_item_status_id')
                ->references('id')
                ->on('order_item_statuses')
                ->onDelete('restrict'); // Protects dictionary records from accidental deletion
        });
    }
};