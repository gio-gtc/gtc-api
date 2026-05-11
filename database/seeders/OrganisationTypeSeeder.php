<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OrganisationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Promoter'],
            ['name' => 'Talent Agency'],
            ['name' => 'Voice Over'],
            ['name' => 'Venue'],
            ['name' => 'Media Outlet'],
            ['name' => 'Advertising Agency'],
            ['name' => 'Graphic Designer'],
            ['name' => 'Record Label'],
            ['name' => 'Utilities'],
        ];

        // Insert or ignore prevents errors if you run the seeder twice
        DB::table('organisation_types')->insertOrIgnore($types);
    }
}