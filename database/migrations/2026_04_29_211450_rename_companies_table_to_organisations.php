<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('companies', 'organisations');
    }

    public function down(): void
    {
        Schema::rename('organisations', 'companies');
    }
};
