<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'United States',  'code' => 'US', 'currency_code' => 'USD', 'dial_code' => '+1'],
            ['name' => 'Canada',         'code' => 'CA', 'currency_code' => 'CAD', 'dial_code' => '+1'],
            ['name' => 'United Kingdom', 'code' => 'GB', 'currency_code' => 'GBP', 'dial_code' => '+44'],
            ['name' => 'Ireland',        'code' => 'IE', 'currency_code' => 'EUR', 'dial_code' => '+353'],
            ['name' => 'Mexico',         'code' => 'MX', 'currency_code' => 'MXN', 'dial_code' => '+52'],
            ['name' => 'Australia',      'code' => 'AU', 'currency_code' => 'AUD', 'dial_code' => '+61'],
            ['name' => 'New Zealand',    'code' => 'NZ', 'currency_code' => 'NZD', 'dial_code' => '+64'],
        ];

        foreach ($countries as $country) {
            Country::firstOrCreate(
                ['code' => $country['code']], // Look for this code first
                [
                    'name' => $country['name'],
                    'currency_code' => $country['currency_code'],
                    'dial_code' => $country['dial_code']
                ]
            );
        }
    }
}