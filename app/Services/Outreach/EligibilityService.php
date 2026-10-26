<?php

namespace App\Services\Outreach;

use App\Models\DomainMapping;
use App\Models\Lead;
use Carbon\CarbonImmutable;

class EligibilityService
{
    public function __construct(private readonly SuppressionService $suppressionService)
    {
    }

    /**
     * @return array{status:string,reasons:array<int,string>}
     */
    public function evaluateLead(Lead $lead): array
    {
        $reasons = [];
        $status = 'unknown';

        $email = strtolower(trim($lead->email));
        $domain = strtolower($lead->email_domain);

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $reasons[] = 'E001_INVALID_EMAIL';
            return $this->persist($lead, 'ineligible', $reasons);
        }

        if ($this->suppressionService->isSuppressed($email, $domain)) {
            $reasons[] = 'E006_SUPPRESSED';
            return $this->persist($lead, 'ineligible', $reasons);
        }

        $source = $lead->sources()->latest('source_retrieved_at')->first();
        if (! $source) {
            $reasons[] = 'E004_MISSING_EVIDENCE';
            return $this->persist($lead, 'ineligible', $reasons);
        }

        if (in_array($source->crawl_visibility, ['authenticated', 'paywalled', 'blocked'], true)) {
            $reasons[] = 'E002_NON_PUBLIC_SOURCE';
            return $this->persist($lead, 'ineligible', $reasons);
        }

        if (! $source->http_status || $source->http_status < 200 || $source->http_status >= 300) {
            $reasons[] = 'E003_SOURCE_UNAVAILABLE';
            return $this->persist($lead, 'ineligible', $reasons);
        }

        if (
            empty($source->source_url)
            || empty($source->source_retrieved_at)
            || empty($source->page_text_snippet)
            || empty($source->source_domain)
        ) {
            $reasons[] = 'E004_MISSING_EVIDENCE';
            return $this->persist($lead, 'ineligible', $reasons);
        }

        if (empty($lead->target_business_domain)) {
            $reasons[] = 'U002_OWNERSHIP_UNRESOLVED';
            return $this->persist($lead, 'unknown', $reasons);
        }

        $targetDomain = strtolower($lead->target_business_domain);
        $sourceDomain = strtolower($source->source_domain);

        $domainMatch = $sourceDomain === $targetDomain;
        $mappedMatch = DomainMapping::query()
            ->where('business_domain', $targetDomain)
            ->where('approved_source_domain', $sourceDomain)
            ->exists();

        if (! $domainMatch && ! $mappedMatch) {
            $reasons[] = 'U002_OWNERSHIP_UNRESOLVED';
            return $this->persist($lead, 'unknown', $reasons);
        }

        $status = 'eligible';
        $reasons[] = 'P001_PUBLIC_SOURCE';
        $reasons[] = 'P003_DOMAIN_RELEVANCE_CONFIRMED';
        $reasons[] = 'P004_EVIDENCE_COMPLETE';

        return $this->persist($lead, $status, $reasons);
    }

    /**
     * @return array{status:string,reasons:array<int,string>}
     */
    private function persist(Lead $lead, string $status, array $reasons): array
    {
        $lead->update([
            'status' => $status,
            'status_reason_codes' => array_values(array_unique($reasons)),
            'policy_version' => config('outreach.policy_version', 'v1'),
            'last_verified_at' => CarbonImmutable::now(),
            'first_seen_at' => $lead->first_seen_at ?? CarbonImmutable::now(),
        ]);

        return [
            'status' => $status,
            'reasons' => $lead->status_reason_codes ?? [],
        ];
    }
}
