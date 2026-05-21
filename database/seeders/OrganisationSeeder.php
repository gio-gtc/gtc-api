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
        $organisations = Organisation::factory(4)->create();

        $organisations->each(function ($org) {
            $randomTypes = OrganisationType::where('id', '!=', 3) // 💡 Skip type 3 for now
                ->inRandomOrder()
                ->take(rand(1, 2))
                ->pluck('id');
                
            $org->types()->attach($randomTypes);
        });

        // Pick exactly 2 random organisations from the collection and attach type 3
        $organisations->random(2)->each(function ($org) {
            $org->types()->attach(3);
        });
    }
}