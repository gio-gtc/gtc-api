<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            
            $table->string('name'); // Required
            $table->string('billing_address')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            
            // Assuming you will have a 'countries' table later.
            $table->unsignedBigInteger('country_id')->nullable();

            // Currency ID (links to countries table)
            $table->unsignedBigInteger('currency_id')->nullable();
            
            // decimal('column_name', total_digits, decimal_places)
            $table->decimal('discount_rate', 5, 2)->default(0); 
            $table->decimal('credit_limit', 12, 2)->nullable();
            $table->string('credit_terms')->nullable();

            $table->string('accounts_payable_contact')->nullable();
            $table->json('accounts_payable_emails')->nullable();
            
            $table->string('pay_email')->nullable();
            $table->string('rec_email')->nullable();
            $table->string('copy_email')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('fax_number')->nullable();
            
            // These will be encrypted by the Model, but they need to be 
            // text columns in the DB because encrypted strings are very long.
            $table->text('bank_account_number')->nullable();
            $table->text('routing_number')->nullable();
            $table->string('rec_name')->nullable();
            $table->string('rec_tel')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
