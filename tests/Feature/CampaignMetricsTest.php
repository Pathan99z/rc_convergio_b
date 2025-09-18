<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_campaigns_metrics_includes_total_campaigns()
    {
        // Create a test user with tenant_id
        $user = User::factory()->create([
            'tenant_id' => 5,
            'organization_name' => 'Test Organization'
        ]);

        // Create some test campaigns for the tenant
        Campaign::factory()->count(3)->create([
            'tenant_id' => 5,
            'created_by' => $user->id,
            'delivered_count' => 10,
            'opened_count' => 5,
            'clicked_count' => 2,
            'bounced_count' => 1,
        ]);

        // Create campaigns for a different tenant (should not be counted)
        Campaign::factory()->count(2)->create([
            'tenant_id' => 6, // Different tenant
            'created_by' => $user->id,
            'delivered_count' => 20,
            'opened_count' => 10,
            'clicked_count' => 5,
            'bounced_count' => 2,
        ]);

        // Make authenticated request to campaigns metrics endpoint
        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics');

        // Assert successful response
        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'total_campaigns',
                    'delivered',
                    'opens',
                    'clicks',
                    'bounces',
                    'range'
                ]
            ]);

        // Assert the response data
        $data = $response->json('data');
        
        // Should only count campaigns for tenant_id = 5
        $this->assertEquals(3, $data['total_campaigns']);
        
        // Other metrics should be calculated correctly for the tenant
        $this->assertEquals(30, $data['delivered']); // 3 campaigns * 10 delivered each
        $this->assertEquals(15, $data['opens']);     // 3 campaigns * 5 opens each
        $this->assertEquals(6, $data['clicks']);     // 3 campaigns * 2 clicks each
        $this->assertEquals(3, $data['bounces']);    // 3 campaigns * 1 bounce each
        $this->assertEquals('14d', $data['range']);  // Default range
    }

    public function test_campaigns_metrics_respects_tenant_isolation()
    {
        // Create two users with different tenant_ids
        $user1 = User::factory()->create(['tenant_id' => 1]);
        $user2 = User::factory()->create(['tenant_id' => 2]);

        // Create campaigns for each tenant
        Campaign::factory()->count(2)->create([
            'tenant_id' => 1,
            'created_by' => $user1->id,
        ]);

        Campaign::factory()->count(4)->create([
            'tenant_id' => 2,
            'created_by' => $user2->id,
        ]);

        // Test user1 should only see their tenant's campaigns
        $response1 = $this->actingAs($user1)
            ->getJson('/api/campaigns/metrics');

        $response1->assertStatus(200);
        $this->assertEquals(2, $response1->json('data.total_campaigns'));

        // Test user2 should only see their tenant's campaigns
        $response2 = $this->actingAs($user2)
            ->getJson('/api/campaigns/metrics');

        $response2->assertStatus(200);
        $this->assertEquals(4, $response2->json('data.total_campaigns'));
    }

    public function test_campaigns_metrics_handles_empty_campaigns()
    {
        // Create a user with no campaigns
        $user = User::factory()->create(['tenant_id' => 5]);

        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(0, $data['total_campaigns']);
        $this->assertEquals(0, $data['delivered']);
        $this->assertEquals(0, $data['opens']);
        $this->assertEquals(0, $data['clicks']);
        $this->assertEquals(0, $data['bounces']);
    }

    public function test_campaigns_metrics_backward_compatibility()
    {
        // Create a user and campaigns
        $user = User::factory()->create(['tenant_id' => 5]);
        
        Campaign::factory()->create([
            'tenant_id' => 5,
            'created_by' => $user->id,
            'delivered_count' => 100,
            'opened_count' => 50,
            'clicked_count' => 25,
            'bounced_count' => 5,
        ]);

        $response = $this->actingAs($user)
            ->getJson('/api/campaigns/metrics');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Ensure all existing fields are still present and working
        $this->assertArrayHasKey('total_campaigns', $data);
        $this->assertArrayHasKey('delivered', $data);
        $this->assertArrayHasKey('opens', $data);
        $this->assertArrayHasKey('clicks', $data);
        $this->assertArrayHasKey('bounces', $data);
        $this->assertArrayHasKey('range', $data);
        
        // Verify the values match expected format
        $this->assertEquals(1, $data['total_campaigns']);
        $this->assertEquals(100, $data['delivered']);
        $this->assertEquals(50, $data['opens']);
        $this->assertEquals(25, $data['clicks']);
        $this->assertEquals(5, $data['bounces']);
        $this->assertEquals('14d', $data['range']);
    }
}
