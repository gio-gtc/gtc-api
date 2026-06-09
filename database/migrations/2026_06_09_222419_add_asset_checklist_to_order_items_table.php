<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->boolean('audio_received')->nullable()->default(null)->index();
            $table->boolean('voice_over_received')->nullable()->default(null)->index();
            $table->boolean('art_received')->nullable()->default(null)->index();
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['audio_received', 'voice_over_received', 'art_received']);
        });
    }
};