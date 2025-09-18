<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CampaignTrendsTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaigns_trends_returns_daily_data()
    {
        // Create a test user with tenant_id
        $user = User::factory()->create([
            'tenant_id' => 5,
            'organization_name' => 'Test Organization'
        ]);

        // Create a test campaign
        $campaign = Campaign::factory()->create([
            'tenant_id' => 5,
            'created_by' => $user->id,
            'status' => 'sent',
            'sent_at' => now()->subDays(2),
        ]);

        // Create campaign recipients with different timestamps
        $today = now();
        $yesterday = now()->subDay();
        $twoDaysAgo = now()->subDays(2);

        // Today's recipients
        CampaignRecipient::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => $today,
            'delivered_at' => $today,
            'opened_at' => $today,
            'clicked_at' => $today,
        ]);

        // Yesterday's recipients
        CampaignRecipient::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => $yesterday,
            'delivered_at' => $yesterday,
            'opened_at' => $yesterday,
            'bounced_at' => $yesterday,
        ]);

        // Two days ago recipients
        CampaignRecipient::factory()->count(1)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => $twoDaysAgo,
            'delivered_at' => $twoDaysAgo,
        ]);

        // Make authenticated request to campaigns trends endpoint
        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics/trends?range=7d&interval=daily');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    '*' => [
                        'date',
                        'sent',
                        'delivered',
                        'opens',
                        'clicks',
                        'bounces'
                    ]
                ]
            ]);

        $data = $response->json('data');
        
        // Should have data for multiple days
        $this->assertGreaterThan(1, count($data));
        
        // Find today's data
        $todayData = collect($data)->firstWhere('date', $today->format('Y-m-d'));
        $this->assertNotNull($todayData);
        $this->assertEquals(3, $todayData['sent']);
        $this->assertEquals(3, $todayData['delivered']);
        $this->assertEquals(3, $todayData['opens']);
        $this->assertEquals(3, $todayData['clicks']);
        $this->assertEquals(0, $todayData['bounces']);

        // Find yesterday's data
        $yesterdayData = collect($data)->firstWhere('date', $yesterday->format('Y-m-d'));
        $this->assertNotNull($yesterdayData);
        $this->assertEquals(2, $yesterdayData['sent']);
        $this->assertEquals(2, $yesterdayData['delivered']);
        $this->assertEquals(2, $yesterdayData['opens']);
        $this->assertEquals(0, $yesterdayData['clicks']);
        $this->assertEquals(2, $yesterdayData['bounces']);
    }

    public function test_campaigns_trends_respects_tenant_isolation()
    {
        // Create two users with different tenant_ids
        $user1 = User::factory()->create(['tenant_id' => 1]);
        $user2 = User::factory()->create(['tenant_id' => 2]);

        // Create campaigns for each tenant
        $campaign1 = Campaign::factory()->create([
            'tenant_id' => 1,
            'created_by' => $user1->id,
            'status' => 'sent',
        ]);

        $campaign2 = Campaign::factory()->create([
            'tenant_id' => 2,
            'created_by' => $user2->id,
            'status' => 'sent',
        ]);

        // Create recipients for each tenant
        CampaignRecipient::factory()->count(3)->create([
            'campaign_id' => $campaign1->id,
            'tenant_id' => 1,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        CampaignRecipient::factory()->count(5)->create([
            'campaign_id' => $campaign2->id,
            'tenant_id' => 2,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        // Test user1 should only see their tenant's data
        $response1 = $this->actingAs($user1)
            ->getJson('/api/campaigns/metrics/trends?range=7d');

        $response1->assertStatus(200);
        $data1 = $response1->json('data');
        $totalSent1 = collect($data1)->sum('sent');
        $this->assertEquals(3, $totalSent1);

        // Test user2 should only see their tenant's data
        $response2 = $this->actingAs($user2)
            ->getJson('/api/campaigns/metrics/trends?range=7d');

        $response2->assertStatus(200);
        $data2 = $response2->json('data');
        $totalSent2 = collect($data2)->sum('sent');
        $this->assertEquals(5, $totalSent2);
    }

    public function test_campaigns_trends_supports_weekly_interval()
    {
        // Create a test user
        $user = User::factory()->create(['tenant_id' => 5]);

        // Create a campaign
        $campaign = Campaign::factory()->create([
            'tenant_id' => 5,
            'created_by' => $user->id,
            'status' => 'sent',
        ]);

        // Create recipients across different weeks
        $thisWeek = now();
        $lastWeek = now()->subWeek();

        CampaignRecipient::factory()->count(4)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => $thisWeek,
            'delivered_at' => $thisWeek,
        ]);

        CampaignRecipient::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => $lastWeek,
            'delivered_at' => $lastWeek,
        ]);

        // Request weekly data
        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics/trends?range=14d&interval=weekly');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));
        
        // Check that dates are in YYYY-MM-DD format
        foreach ($data as $item) {
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $item['date']);
            $this->assertIsInt($item['sent']);
            $this->assertIsInt($item['delivered']);
            $this->assertIsInt($item['opens']);
            $this->assertIsInt($item['clicks']);
            $this->assertIsInt($item['bounces']);
        }
    }

    public function test_campaigns_trends_handles_empty_data()
    {
        // Create a user with no campaigns
        $user = User::factory()->create(['tenant_id' => 5]);

        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics/trends?range=7d');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertIsArray($data);
        $this->assertEmpty($data);
    }

    public function test_campaigns_trends_default_parameters()
    {
        // Create a test user
        $user = User::factory()->create(['tenant_id' => 5]);

        // Create a campaign with recipients
        $campaign = Campaign::factory()->create([
            'tenant_id' => 5,
            'created_by' => $user->id,
            'status' => 'sent',
        ]);

        CampaignRecipient::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => now(),
            'delivered_at' => now(),
        ]);

        // Request without parameters (should use defaults: range=30d, interval=daily)
        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics/trends');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertIsArray($data);
        
        // Should have at least one data point
        $this->assertGreaterThan(0, count($data));
        
        // Check data structure
        $firstItem = $data[0];
        $this->assertArrayHasKey('date', $firstItem);
        $this->assertArrayHasKey('sent', $firstItem);
        $this->assertArrayHasKey('delivered', $firstItem);
        $this->assertArrayHasKey('opens', $firstItem);
        $this->assertArrayHasKey('clicks', $firstItem);
        $this->assertArrayHasKey('bounces', $firstItem);
    }

    public function test_campaigns_trends_filters_by_date_range()
    {
        // Create a test user
        $user = User::factory()->create(['tenant_id' => 5]);

        // Create a campaign
        $campaign = Campaign::factory()->create([
            'tenant_id' => 5,
            'created_by' => $user->id,
            'status' => 'sent',
        ]);

        // Create recipients in different time periods
        $recent = now()->subDays(2);
        $old = now()->subDays(10);

        CampaignRecipient::factory()->count(3)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => $recent,
            'delivered_at' => $recent,
        ]);

        CampaignRecipient::factory()->count(2)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => $old,
            'delivered_at' => $old,
        ]);

        // Request only last 5 days (should exclude the old data)
        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics/trends?range=5d');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $totalSent = collect($data)->sum('sent');
        
        // Should only include the recent data (3 recipients)
        $this->assertEquals(3, $totalSent);
    }

    public function test_campaigns_trends_response_format()
    {
        // Create a test user
        $user = User::factory()->create(['tenant_id' => 5]);

        // Create a campaign with recipients
        $campaign = Campaign::factory()->create([
            'tenant_id' => 5,
            'created_by' => $user->id,
            'status' => 'sent',
        ]);

        CampaignRecipient::factory()->count(1)->create([
            'campaign_id' => $campaign->id,
            'tenant_id' => 5,
            'sent_at' => now(),
            'delivered_at' => now(),
            'opened_at' => now(),
            'clicked_at' => now(),
            'bounced_at' => now(),
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics/trends?range=7d');

        $response->assertStatus(200);
        
        // Verify response structure
        $response->assertJsonStructure([
            'success',
            'data' => [
                '*' => [
                    'date',
                    'sent',
                    'delivered', 
                    'opens',
                    'clicks',
                    'bounces'
                ]
            ]
        ]);

        // Verify success field
        $this->assertTrue($response->json('success'));
        
        // Verify data types
        $data = $response->json('data');
        if (!empty($data)) {
            $firstItem = $data[0];
            $this->assertIsString($firstItem['date']);
            $this->assertIsInt($firstItem['sent']);
            $this->assertIsInt($firstItem['delivered']);
            $this->assertIsInt($firstItem['opens']);
            $this->assertIsInt($firstItem['clicks']);
            $this->assertIsInt($firstItem['bounces']);
        }
    }
}
