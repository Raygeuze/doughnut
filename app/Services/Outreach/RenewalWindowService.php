<?php

namespace App\Services\Outreach;

use App\Models\Lead;
use Carbon\CarbonImmutable;

class RenewalWindowService
{
    public function __construct(private readonly OutreachSettings $settings)
    {
    }

    public function blockReason(Lead $lead): ?string
    {
        $enabled = $this->settings->getBool(
            'outreach_renewal_window_enabled',
            (bool) config('outreach.renewal_window.enabled', true),
        );

        if (! $enabled) {
            return null;
        }

        $leadDays = max(0, $this->settings->getInt(
            'outreach_renewal_window_lead_days',
            (int) config('outreach.renewal_window.lead_days', 30),
        ));

        $lagDays = max(0, $this->settings->getInt(
            'outreach_renewal_window_lag_days',
            (int) config('outreach.renewal_window.lag_days', 14),
        ));

        $allowWithoutSignal = $this->settings->getBool(
            'outreach_renewal_window_allow_without_signal',
            (bool) config('outreach.renewal_window.allow_without_signal', false),
        );

        $nextBusiness = $lead->next_business_renewal_at;
        $nextDomain = $lead->next_domain_renewal_at;
        $targetDate = null;

        if ($nextBusiness && $nextDomain) {
            $targetDate = $nextBusiness->lte($nextDomain) ? $nextBusiness : $nextDomain;
        } elseif ($nextBusiness) {
            $targetDate = $nextBusiness;
        } elseif ($nextDomain) {
            $targetDate = $nextDomain;
        }

        if (! $targetDate) {
            return $allowWithoutSignal ? null : 'renewal_signal_missing';
        }

        $now = CarbonImmutable::now();
        $windowStart = CarbonImmutable::instance($targetDate)->subDays($leadDays);
        $windowEnd = CarbonImmutable::instance($targetDate)->addDays($lagDays);

        if ($now->lt($windowStart) || $now->gt($windowEnd)) {
            return 'renewal_window_not_open';
        }

        return null;
    }
}
