<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_item_broadcast_specifications', function (Blueprint $table) {
            $table->id();
            $table->string('type')->index();
            $table->string('cut');
            $table->integer('duration_seconds')->index();
            $table->string('language');
            $table->string('encoding')->nullable()->index();
            $table->string('encoding_custom')->nullable();
            $table->string('isci')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_item_broadcast_specifications');
    }
};