<?php

namespace Database\Seeders;

use App\Models\Campaign;
use Illuminate\Database\Seeder;

class CampaignSeeder extends Seeder
{
    public function run(): void
    {
        // Create a few template campaigns per tenant 1 as examples; safe to re-run
        $templates = [
            ['name' => 'Welcome Series', 'subject' => 'Welcome to RC Convergio', 'content' => '<p>Welcome!</p>'],
            ['name' => 'Event Invite', 'subject' => 'Join our upcoming event', 'content' => '<p>Save your seat</p>'],
            ['name' => 'Product Update', 'subject' => 'Latest product updates', 'content' => '<p>What\'s new</p>'],
        ];

        foreach ($templates as $tpl) {
            Campaign::firstOrCreate(
                [
                    'tenant_id' => 1,
                    'name' => $tpl['name'],
                    'is_template' => true,
                ],
                [
                    'description' => null,
                    'subject' => $tpl['subject'],
                    'content' => $tpl['content'],
                    'status' => 'draft',
                    'type' => 'email',
                    'created_by' => 1,
                ]
            );
        }
    }
}
