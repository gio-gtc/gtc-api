<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('venue_id')->constrained('venues')->restrictOnDelete();
            $table->foreignId('ordered_by_id')->constrained('users')->restrictOnDelete();
            
            // Defaulting status directly in DB matching your rule
            $table->string('status')->default('New Order'); 
            $table->string('local_deliverable_email')->nullable();
            $table->date('due_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};