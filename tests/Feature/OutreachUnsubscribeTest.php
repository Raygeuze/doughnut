<?php

namespace Tests\Feature;

use App\Models\Lead;
use App\Models\SuppressionEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class OutreachUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_unsubscribe_suppresses_email_and_domain(): void
    {
        $lead = Lead::query()->create([
            'email' => 'owner@sample.com',
            'target_business_domain' => 'sample.com',
            'status' => 'eligible',
            'first_seen_at' => now(),
        ]);

        $url = URL::temporarySignedRoute(
            'outreach.unsubscribe',
            now()->addHour(),
            ['lead' => $lead->id, 'campaign' => 42],
        );

        $response = $this->get($url);
        $response->assertOk();

        $this->assertDatabaseHas('suppression_list', [
            'scope' => 'email',
            'email' => 'owner@sample.com',
            'reason' => 'unsubscribe',
        ]);

        $this->assertDatabaseHas('suppression_list', [
            'scope' => 'domain',
            'email_domain' => 'sample.com',
            'reason' => 'unsubscribe',
        ]);

        $this->assertSame(2, SuppressionEntry::query()->count());
    }
}
