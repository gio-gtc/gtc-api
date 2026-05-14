<?php

namespace Database\Seeders;

use App\Models\Organisation;
use App\Models\OrganisationType;
use Illuminate\Database\Seeder;

class OrganisationSeeder extends Seeder
{
    public function run(): void
    {
        $gtc = Organisation::create([
            'name' => 'Global Tour Creatives',
            'billing_address' => '123 Fake Street',
            'city' => 'Somewhere',
            'state' => 'Big Land',
            'zip' => 'None',
            'country_id' => 1,
            'credit_limit' => 10000,
            'credit_terms' => '3 days',
            'currency_code' => "USD",
            'accounts_payable_contact' => 'billing@gtc.co',
            'accounts_payable_emails' => [
                'billing@gtc.co',
                'russ@gtc.co',
                'david@gtc.co'
            ]
        ]);

        $gtc->types()->attach([1]);
        
        // TODO: Production Remove
        Organisation::factory(9)->create()->each(function ($org) {
            $randomTypes = OrganisationType::inRandomOrder()->take(rand(1, 2))->pluck('id');
            $org->types()->attach($randomTypes);
        });
    }
}