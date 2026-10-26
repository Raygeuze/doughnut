<?php

namespace App\Services\Outreach\Registry;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Models\RegistryBusinessDomain;
use App\Models\RegistryCrawlRun;
use App\Services\Outreach\AuditLogger;
use App\Services\Outreach\EligibilityService;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;

class PublicContactCrawlerService
{
    public function __construct(
        private readonly EligibilityService $eligibility,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * @return array{domains:int,pages:int,emails:int,leads:int}
     */
    public function crawl(int $limitDomains = 50): array
    {
        $domains = 0;
        $pages = 0;
        $emails = 0;
        $leads = 0;

        RegistryBusinessDomain::query()
            ->with('seedBusiness')
            ->where('status', 'resolved')
            ->orderBy('evaluated_at', 'desc')
            ->limit(max(1, $limitDomains))
            ->get()
            ->each(function (RegistryBusinessDomain $domainRecord) use (&$domains, &$pages, &$emails, &$leads): void {
                $domains++;
                $seed = $domainRecord->seedBusiness;
                if (! $seed) {
                    return;
                }

                $renewal = $this->inferRenewalSignals($seed->raw_payload ?? []);

                $urls = [
                    'https://'.$domainRecord->candidate_domain,
                    'https://'.$domainRecord->candidate_domain.'/contact',
                    'https://'.$domainRecord->candidate_domain.'/contact-us',
                    'https://'.$domainRecord->candidate_domain.'/about',
                ];

                foreach ($urls as $url) {
                    $pages++;
                    $page = $this->fetchPage($url);

                    RegistryCrawlRun::query()->create([
                        'registry_business_domain_id' => $domainRecord->id,
                        'url' => $url,
                        'http_status' => $page['http_status'],
                        'crawl_visibility' => $page['crawl_visibility'],
                        'page_title' => $page['page_title'],
                        'page_text_snippet' => $page['snippet'],
                        'emails_found' => count($page['emails']),
                        'crawled_at' => CarbonImmutable::now(),
                    ]);

                    foreach ($page['emails'] as $email) {
                        $emails++;
                        $lead = Lead::query()->firstOrCreate(
                            ['email' => strtolower($email)],
                            [
                                'target_business_name' => $seed->business_name,
                                'target_business_domain' => $domainRecord->candidate_domain,
                                'first_seen_at' => CarbonImmutable::now(),
                                'next_business_renewal_at' => $renewal['next_business_renewal_at'],
                                'next_domain_renewal_at' => $renewal['next_domain_renewal_at'],
                                'renewal_signal_source' => $renewal['signal_source'],
                                'renewal_confidence_score' => $renewal['confidence_score'],
                            ],
                        );

                        $leadPatch = [];
                        if (! $lead->next_business_renewal_at && $renewal['next_business_renewal_at']) {
                            $leadPatch['next_business_renewal_at'] = $renewal['next_business_renewal_at'];
                        }
                        if (! $lead->next_domain_renewal_at && $renewal['next_domain_renewal_at']) {
                            $leadPatch['next_domain_renewal_at'] = $renewal['next_domain_renewal_at'];
                        }
                        if (! $lead->renewal_signal_source && $renewal['signal_source']) {
                            $leadPatch['renewal_signal_source'] = $renewal['signal_source'];
                        }
                        if ((int) $lead->renewal_confidence_score === 0 && $renewal['confidence_score'] > 0) {
                            $leadPatch['renewal_confidence_score'] = $renewal['confidence_score'];
                        }
                        if ($leadPatch !== []) {
                            $lead->update($leadPatch);
                        }

                        $sourceDomain = parse_url($url, PHP_URL_HOST);
                        $sourceDomain = is_string($sourceDomain) ? strtolower($sourceDomain) : $domainRecord->candidate_domain;
                        $evidencePayload = [
                            $url,
                            $sourceDomain,
                            (string) $page['http_status'],
                            (string) $page['crawl_visibility'],
                            (string) $page['snippet'],
                        ];

                        LeadSource::query()->firstOrCreate(
                            [
                                'lead_id' => $lead->id,
                                'source_url' => $url,
                                'evidence_hash' => hash('sha256', json_encode($evidencePayload)),
                            ],
                            [
                                'source_domain' => $sourceDomain,
                                'source_retrieved_at' => CarbonImmutable::now(),
                                'http_status' => $page['http_status'],
                                'crawl_visibility' => $page['crawl_visibility'],
                                'page_title' => $page['page_title'],
                                'page_text_snippet' => $page['snippet'],
                                'email_found_context' => $page['snippet'],
                            ],
                        );

                        $this->eligibility->evaluateLead($lead->fresh());
                        $leads++;

                        $this->audit->log(
                            entityType: 'lead',
                            entityId: $lead->id,
                            action: 'lead_discovered_by_public_crawl',
                            sourceSystem: 'public_contact_crawl',
                            sourceIdentifier: $domainRecord->candidate_domain,
                            payload: [
                                'email' => strtolower($email),
                                'url' => $url,
                                'http_status' => $page['http_status'],
                                'visibility' => $page['crawl_visibility'],
                            ],
                        );
                    }
                }
            });

        return [
            'domains' => $domains,
            'pages' => $pages,
            'emails' => $emails,
            'leads' => $leads,
        ];
    }

    /**
     * @return array{http_status:int,crawl_visibility:string,page_title:?string,snippet:string,emails:array<int,string>}
     */
    private function fetchPage(string $url): array
    {
        try {
            $response = Http::retry(1, 500)
                ->timeout(15)
                ->withHeaders(['User-Agent' => 'Doughnut-Public-Contact-Crawler/1.0'])
                ->get($url);

            $body = (string) $response->body();
            $text = trim(strip_tags($body));
            $snippet = mb_substr(preg_replace('/\s+/', ' ', $text) ?? '', 0, 1200);
            $title = null;

            if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $body, $m) === 1) {
                $title = trim(strip_tags($m[1]));
            }

            preg_match_all('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $body, $matches);
            $emails = array_values(array_unique(array_map('strtolower', $matches[0] ?? [])));

            return [
                'http_status' => $response->status(),
                'crawl_visibility' => $response->successful() ? 'public' : 'unknown',
                'page_title' => $title,
                'snippet' => $snippet,
                'emails' => $emails,
            ];
        } catch (\Throwable) {
            return [
                'http_status' => 0,
                'crawl_visibility' => 'unknown',
                'page_title' => null,
                'snippet' => '',
                'emails' => [],
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{next_business_renewal_at:?CarbonInterface,next_domain_renewal_at:?CarbonInterface,signal_source:?string,confidence_score:int}
     */
    private function inferRenewalSignals(array $payload): array
    {
        if ($payload === []) {
            return [
                'next_business_renewal_at' => null,
                'next_domain_renewal_at' => null,
                'signal_source' => null,
                'confidence_score' => 0,
            ];
        }

        $businessKeyCandidates = [
            'business_name_renewal_date',
            'name_renewal_date',
            'renewal_date',
            'abn_status_from_date',
            'registration_date',
            'entity_registration_date',
        ];

        $domainKeyCandidates = [
            'domain_renewal_date',
            'domain_expiry_date',
            'domain_expiry',
            'expiry_date',
        ];

        $businessDate = $this->extractDateFromPayload($payload, $businessKeyCandidates);
        $domainDate = $this->extractDateFromPayload($payload, $domainKeyCandidates);

        $nextBusiness = $businessDate ? $this->nextAnnualOccurrence($businessDate) : null;
        $nextDomain = $domainDate ? $this->nextAnnualOccurrence($domainDate) : null;

        $signalSource = null;
        $confidence = 0;

        if ($businessDate) {
            $signalSource = 'seed_raw_payload:business_date';
            $confidence = max($confidence, 75);
        }
        if ($domainDate) {
            $signalSource = $signalSource
                ? $signalSource.'+domain_date'
                : 'seed_raw_payload:domain_date';
            $confidence = max($confidence, 85);
        }

        return [
            'next_business_renewal_at' => $nextBusiness,
            'next_domain_renewal_at' => $nextDomain,
            'signal_source' => $signalSource,
            'confidence_score' => $confidence,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<int, string> $keys
     */
    private function extractDateFromPayload(array $payload, array $keys): ?CarbonImmutable
    {
        $flat = $this->flattenPayload($payload);

        foreach ($keys as $key) {
            if (! isset($flat[$key])) {
                continue;
            }

            $parsed = $this->parseDateValue($flat[$key]);
            if ($parsed) {
                return $parsed;
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     */
    private function parseDateValue(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        $candidate = trim($value);
        if ($candidate === '') {
            return null;
        }

        $formats = [
            'Y-m-d',
            'd/m/Y',
            'Y/m/d',
            'd-m-Y',
            DATE_ATOM,
            'Y-m-d\TH:i:sP',
        ];

        foreach ($formats as $format) {
            try {
                $parsed = CarbonImmutable::createFromFormat($format, $candidate);
                if ($parsed !== false) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return CarbonImmutable::parse($candidate)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nextAnnualOccurrence(CarbonImmutable $anchor): CarbonImmutable
    {
        $now = CarbonImmutable::now()->startOfDay();
        $candidate = $anchor->year($now->year)->startOfDay();

        if ($candidate->lt($now)) {
            $candidate = $candidate->addYear();
        }

        return $candidate;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function flattenPayload(array $payload): array
    {
        $flat = [];

        $walk = function (array $node, string $prefix = '') use (&$walk, &$flat): void {
            foreach ($node as $key => $value) {
                $normalizedKey = strtolower(trim((string) $key));
                $path = $prefix === '' ? $normalizedKey : $prefix.'.'.$normalizedKey;

                if (is_array($value)) {
                    $walk($value, $path);
                    continue;
                }

                $flat[$normalizedKey] = $value;
                $flat[$path] = $value;
            }
        };

        $walk($payload);

        return $flat;
    }
}
