<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->date('start_date');
            $table->date('expire_on_sale_now_cuts');
            
            // Foreign Keys (pointing to users and departments with restrictive deletion)
            $table->foreignId('gtc_rep_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('voice_over_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();

            // Booleans defaulting to false
            $table->boolean('hold_all_invoices')->default(false);
            $table->boolean('live_on_ordering_system')->default(false);
            $table->boolean('require_client_approval')->default(false);

            // Optional Nullable strings
            $table->string('client_approval_email')->nullable();
            $table->string('tour_sponsor')->nullable();
            $table->mediumText('special_instructions')->nullable();

            // Financial fields defaulting to null using decimal(10,2)
            $table->decimal('tv_first_cut', 10, 2)->nullable();
            $table->decimal('tv_second_cut', 10, 2)->nullable();
            $table->decimal('radio_single_duration', 10, 2)->nullable();
            $table->decimal('radio_dual_duration', 10, 2)->nullable();
            $table->decimal('key_art', 10, 2)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tours');
    }
};
