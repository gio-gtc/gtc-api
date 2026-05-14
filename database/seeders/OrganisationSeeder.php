<?php

namespace Database\Seeders;

use App\Models\Organisation;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organisation::create([
            'name' => 'Global Tour Creatives',
            'billing_address' => '123 Fake Street',
            'city' => 'Somewhere',
            'state' => 'Big Land',
            'zip' => 'None',
            'country_id' => 1,
            'credit_limit' => 10000,
            'credit_terms' => '3 days',
            'currency_id' => 1,
            'accounts_payable_contact' => 'billing@gtc.co',
            'accounts_payable_emails' => [
                'billing@gtc.co',
                'russ@gtc.co',
                'david@gtc.co'
            ]
        ]);

        $org->types()->attach([1]);
        
        $acme = Organisation::create([
            'name' => 'Acme Corp',
            'billing_address' => '456 Desert Road',
            'city' => 'Phoenix',
            'state' => 'AZ',
            'zip' => '85001',
            'country_id' => 1,
            'currency_id' => 1,
        ]);
        
        $acme->types()->attach([2, 6]);
    }
}