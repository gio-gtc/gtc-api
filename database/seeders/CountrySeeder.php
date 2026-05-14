<?php

namespace Database\Seeders;

use App\Models\Country;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
{
    public function run(): void
    {
        $countries = [
            ['name' => 'United States', 'code' => 'US', 'currency_code' => 'USD', 'dial_code' => '+1'],
            ['name' => 'Canada', 'code' => 'CA', 'currency_code' => 'CAD', 'dial_code' => '+1'],
            ['name' => 'United Kingdom', 'code' => 'GB', 'currency_code' => 'GBP', 'dial_code' => '+44'],
            ['name' => 'Australia', 'code' => 'AU', 'currency_code' => 'AUD', 'dial_code' => '+61'],
            ['name' => 'Germany', 'code' => 'DE', 'currency_code' => 'EUR', 'dial_code' => '+49'],
            ['name' => 'France', 'code' => 'FR', 'currency_code' => 'EUR', 'dial_code' => '+33'],
            ['name' => 'Japan', 'code' => 'JP', 'currency_code' => 'JPY', 'dial_code' => '+81'],
            ['name' => 'China', 'code' => 'CN', 'currency_code' => 'CNY', 'dial_code' => '+86'],
            ['name' => 'India', 'code' => 'IN', 'currency_code' => 'INR', 'dial_code' => '+91'],
            ['name' => 'Brazil', 'code' => 'BR', 'currency_code' => 'BRL', 'dial_code' => '+55'],
            ['name' => 'Italy', 'code' => 'IT', 'currency_code' => 'EUR', 'dial_code' => '+39'],
            ['name' => 'Spain', 'code' => 'ES', 'currency_code' => 'EUR', 'dial_code' => '+34'],
            ['name' => 'Mexico', 'code' => 'MX', 'currency_code' => 'MXN', 'dial_code' => '+52'],
            ['name' => 'South Korea', 'code' => 'KR', 'currency_code' => 'KRW', 'dial_code' => '+82'],
            ['name' => 'Russia', 'code' => 'RU', 'currency_code' => 'RUB', 'dial_code' => '+7'],
            ['name' => 'Netherlands', 'code' => 'NL', 'currency_code' => 'EUR', 'dial_code' => '+31'],
            ['name' => 'Switzerland', 'code' => 'CH', 'currency_code' => 'CHF', 'dial_code' => '+41'],
            ['name' => 'Sweden', 'code' => 'SE', 'currency_code' => 'SEK', 'dial_code' => '+46'],
            ['name' => 'Norway', 'code' => 'NO', 'currency_code' => 'NOK', 'dial_code' => '+47'],
            ['name' => 'Denmark', 'code' => 'DK', 'currency_code' => 'DKK', 'dial_code' => '+45'],
            ['name' => 'Finland', 'code' => 'FI', 'currency_code' => 'EUR', 'dial_code' => '+358'],
            ['name' => 'Belgium', 'code' => 'BE', 'currency_code' => 'EUR', 'dial_code' => '+32'],
            ['name' => 'Austria', 'code' => 'AT', 'currency_code' => 'EUR', 'dial_code' => '+43'],
            ['name' => 'Ireland', 'code' => 'IE', 'currency_code' => 'EUR', 'dial_code' => '+353'],
            ['name' => 'New Zealand', 'code' => 'NZ', 'currency_code' => 'NZD', 'dial_code' => '+64'],
            ['name' => 'Singapore', 'code' => 'SG', 'currency_code' => 'SGD', 'dial_code' => '+65'],
            ['name' => 'Malaysia', 'code' => 'MY', 'currency_code' => 'MYR', 'dial_code' => '+60'],
            ['name' => 'South Africa', 'code' => 'ZA', 'currency_code' => 'ZAR', 'dial_code' => '+27'],
            ['name' => 'Argentina', 'code' => 'AR', 'currency_code' => 'ARS', 'dial_code' => '+54'],
            ['name' => 'Chile', 'code' => 'CL', 'currency_code' => 'CLP', 'dial_code' => '+56'],
            ['name' => 'Colombia', 'code' => 'CO', 'currency_code' => 'COP', 'dial_code' => '+57'],
            ['name' => 'Peru', 'code' => 'PE', 'currency_code' => 'PEN', 'dial_code' => '+51'],
            ['name' => 'Egypt', 'code' => 'EG', 'currency_code' => 'EGP', 'dial_code' => '+20'],
            ['name' => 'Nigeria', 'code' => 'NG', 'currency_code' => 'NGN', 'dial_code' => '+234'],
            ['name' => 'Kenya', 'code' => 'KE', 'currency_code' => 'KES', 'dial_code' => '+254'],
            ['name' => 'United Arab Emirates', 'code' => 'AE', 'currency_code' => 'AED', 'dial_code' => '+971'],
            ['name' => 'Saudi Arabia', 'code' => 'SA', 'currency_code' => 'SAR', 'dial_code' => '+966'],
            ['name' => 'Israel', 'code' => 'IL', 'currency_code' => 'ILS', 'dial_code' => '+972'],
            ['name' => 'Turkey', 'code' => 'TR', 'currency_code' => 'TRY', 'dial_code' => '+90'],
            ['name' => 'Poland', 'code' => 'PL', 'currency_code' => 'PLN', 'dial_code' => '+48'],
            ['name' => 'Thailand', 'code' => 'TH', 'currency_code' => 'THB', 'dial_code' => '+66'],
            ['name' => 'Indonesia', 'code' => 'ID', 'currency_code' => 'IDR', 'dial_code' => '+62'],
            ['name' => 'Philippines', 'code' => 'PH', 'currency_code' => 'PHP', 'dial_code' => '+63'],
            ['name' => 'Vietnam', 'code' => 'VN', 'currency_code' => 'VND', 'dial_code' => '+84'],
            ['name' => 'Pakistan', 'code' => 'PK', 'currency_code' => 'PKR', 'dial_code' => '+92'],
            ['name' => 'Bangladesh', 'code' => 'BD', 'currency_code' => 'BDT', 'dial_code' => '+880'],
            ['name' => 'Ukraine', 'code' => 'UA', 'currency_code' => 'UAH', 'dial_code' => '+380'],
            ['name' => 'Portugal', 'code' => 'PT', 'currency_code' => 'EUR', 'dial_code' => '+351'],
            ['name' => 'Greece', 'code' => 'GR', 'currency_code' => 'EUR', 'dial_code' => '+30'],
            ['name' => 'Czech Republic', 'code' => 'CZ', 'currency_code' => 'CZK', 'dial_code' => '+420'],
        ];

        foreach ($countries as $country) {
            Country::updateOrCreate(
                ['name' => $country['name']],
                [
                    'code' => $country['code'],
                    'currency_code' => $country['currency_code'],
                    'dial_code' => $country['dial_code']
                ]
            );
        }
    }
}