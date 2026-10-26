<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignRecipient;
use App\Models\Lead;
use App\Services\Outreach\CampaignOrchestrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OutreachDomainWinnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_build_selects_only_one_winner_per_domain(): void
    {
        $campaign = Campaign::query()->create([
            'name' => 'Fraud Warning #1',
            'purpose' => 'fraud_warning',
            'subject_template' => 'Registry scam warning',
            'body_template' => 'Please ignore fraudulent registry invoices.',
            'status' => 'draft',
        ]);

        $accounts = Lead::query()->create([
            'email' => 'accounts@acme.com',
            'target_business_domain' => 'acme.com',
            'status' => 'eligible',
            'last_verified_at' => now(),
            'first_seen_at' => now(),
        ]);

        $info = Lead::query()->create([
            'email' => 'info@acme.com',
            'target_business_domain' => 'acme.com',
            'status' => 'eligible',
            'last_verified_at' => now()->subMinute(),
            'first_seen_at' => now(),
        ]);

        app(CampaignOrchestrator::class)->buildQueue($campaign);

        $winner = CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('lead_id', $accounts->id)->first();
        $loser = CampaignRecipient::query()->where('campaign_id', $campaign->id)->where('lead_id', $info->id)->first();

        $this->assertNotNull($winner);
        $this->assertNotNull($winner->queued_at);
        $this->assertTrue($winner->is_domain_winner);

        $this->assertNotNull($loser);
        $this->assertNotNull($loser->skipped_at);
        $this->assertSame('domain_primary_not_selected', $loser->skip_reason);
    }
}
