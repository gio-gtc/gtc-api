<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        Schema::table('users', function (Blueprint $table) {
            // avatar as a string (to hold the URL), allowed to be empty
            $table->string('avatar')->nullable()->after('last_name');
            
            // organisations_id as an unsigned big integer (standard for Laravel IDs), allowed to be empty.
            // Note: We are NOT adding a strict foreign key constraint yet because the 'organisations' table does not exist!
            $table->unsignedBigInteger('organisation_id')->nullable()->after('id');
        });
    }

    public function down(): void {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['avatar', 'organisation_id']);
        });
    }
};