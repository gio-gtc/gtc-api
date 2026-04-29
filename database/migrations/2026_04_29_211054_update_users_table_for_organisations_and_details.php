<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Rename the existing column
            $table->renameColumn('company_id', 'organisation_id');

            // 2. Add the new columns (all nullable so it doesn't break existing data)
            $table->string('job_title')->nullable()->after('last_name');
            $table->string('department')->nullable()->after('job_title');
            $table->string('phone_number')->nullable()->after('department');
            $table->text('notes')->nullable()->after('phone_number');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('organisation_id', 'company_id');
            $table->dropColumn(['job_title', 'department', 'phone_number', 'notes']);
        });
    }
};
