<?php

namespace App\Services\Outreach;

use App\Models\SendEvent;
use Carbon\CarbonImmutable;

class EventLogger
{
    public function log(
        string $eventType,
        ?int $leadId,
        ?int $campaignId,
        ?string $reason = null,
        array $metadata = []
    ): void {
        SendEvent::query()->create([
            'lead_id' => $leadId,
            'campaign_id' => $campaignId,
            'event_type' => $eventType,
            'reason_or_error' => $reason,
            'occurred_at' => CarbonImmutable::now(),
            'metadata' => $metadata,
        ]);
    }
}
