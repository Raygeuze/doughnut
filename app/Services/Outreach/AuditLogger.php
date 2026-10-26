<?php

namespace App\Services\Outreach;

use App\Models\OutreachAuditLog;
use Carbon\CarbonImmutable;

class AuditLogger
{
    public function log(
        string $entityType,
        ?int $entityId,
        string $action,
        ?string $sourceSystem = null,
        ?string $sourceIdentifier = null,
        array $payload = []
    ): void {
        OutreachAuditLog::query()->create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action' => $action,
            'source_system' => $sourceSystem,
            'source_identifier' => $sourceIdentifier,
            'payload' => $payload,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }
}
