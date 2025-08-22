<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $contacts = [
            [
                'first_name' => 'Anna',
                'last_name' => 'Smitha',
                'email' => 'anna@example.com',
                'phone' => '11234567890',
                'lifecycle_stage' => 'lead',
                'source' => 'newsletter',
                'owner_id' => 1,
                'tenant_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'first_name' => 'Althaf',
                'last_name' => 'Khan',
                'email' => 'althaf@gmail.com',
                'phone' => '9154433354',
                'lifecycle_stage' => 'customer',
                'source' => 'website',
                'owner_id' => 1,
                'tenant_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'first_name' => 'Jaheer',
                'last_name' => 'Khan',
                'email' => 'zaheer99@gmail.com',
                'phone' => '75896562542',
                'lifecycle_stage' => 'prospect',
                'source' => 'referral',
                'owner_id' => 1,
                'tenant_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        DB::table('contacts')->insert($contacts);
    }
}
