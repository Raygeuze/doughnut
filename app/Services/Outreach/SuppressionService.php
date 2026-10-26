<?php

namespace App\Services\Outreach;

use App\Models\SuppressionEntry;
use Carbon\CarbonImmutable;

class SuppressionService
{
    public function isSuppressed(string $email, string $domain): bool
    {
        return SuppressionEntry::query()
            ->where(function ($query) use ($email, $domain): void {
                $query
                    ->where(fn ($q) => $q->where('scope', 'email')->where('email', $email))
                    ->orWhere(fn ($q) => $q->where('scope', 'domain')->where('email_domain', $domain));
            })
            ->exists();
    }

    public function suppressEmail(
        string $email,
        string $reason,
        string $source,
        array $metadata = []
    ): void {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        SuppressionEntry::query()->create([
            'scope' => 'email',
            'email' => strtolower($email),
            'email_domain' => $domain,
            'reason' => $reason,
            'source' => $source,
            'noted_at' => CarbonImmutable::now(),
            'metadata' => $metadata,
        ]);

        if (
            config('outreach.domain_suppression_propagation_enabled')
            && in_array($reason, ['unsubscribe', 'complaint'], true)
        ) {
            SuppressionEntry::query()->create([
                'scope' => 'domain',
                'email' => null,
                'email_domain' => $domain,
                'reason' => $reason,
                'source' => $source,
                'noted_at' => CarbonImmutable::now(),
                'metadata' => $metadata,
            ]);
        }
    }
}
