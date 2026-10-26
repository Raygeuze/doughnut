<?php

namespace App\Services\Outreach\Registry;

use App\Models\RegistryBusinessDomain;
use App\Models\RegistrySeedBusiness;
use App\Services\Outreach\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

class DomainDiscoveryService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array{processed:int,resolved:int,candidates:int}
     */
    public function discover(int $limit = 200): array
    {
        $processed = 0;
        $resolved = 0;
        $candidates = 0;

        RegistrySeedBusiness::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get()
            ->each(function (RegistrySeedBusiness $business) use (&$processed, &$resolved, &$candidates): void {
                $processed++;
                $domainCandidates = $this->generateDomainCandidates((string) $business->business_name);

                if (empty($domainCandidates)) {
                    $this->audit->log(
                        entityType: 'registry_seed_business',
                        entityId: $business->id,
                        action: 'domain_discovery_no_candidates',
                        sourceSystem: $business->source_system,
                        sourceIdentifier: $business->source_record_id,
                        payload: ['business_name' => $business->business_name],
                    );
                    $business->update(['status' => 'unresolved']);
                    return;
                }

                foreach ($domainCandidates as $candidate) {
                    $probe = $this->probeDomain($candidate);

                    $status = $probe['reachable'] ? 'resolved' : 'candidate';
                    $confidence = $probe['reachable'] ? 85 : 45;
                    $signals = [
                        'reachable' => $probe['reachable'],
                        'http_status' => $probe['http_status'],
                        'probe_url' => $probe['url'],
                        'generated_from_name' => true,
                    ];

                    $domain = RegistryBusinessDomain::query()->updateOrCreate(
                        [
                            'registry_seed_business_id' => $business->id,
                            'candidate_domain' => $candidate,
                        ],
                        [
                            'confidence_score' => $confidence,
                            'status' => $status,
                            'evidence_url' => $probe['url'],
                            'evidence_type' => 'http_probe',
                            'signals' => $signals,
                            'evaluated_at' => CarbonImmutable::now(),
                        ],
                    );

                    $candidates++;

                    $this->audit->log(
                        entityType: 'registry_business_domain',
                        entityId: $domain->id,
                        action: 'domain_candidate_evaluated',
                        sourceSystem: $business->source_system,
                        sourceIdentifier: $business->source_record_id,
                        payload: [
                            'candidate_domain' => $candidate,
                            'confidence_score' => $confidence,
                            'status' => $status,
                            'signals' => $signals,
                        ],
                    );

                    if ($probe['reachable']) {
                        $business->update(['status' => 'resolved']);
                        $resolved++;
                        return;
                    }
                }

                $business->update(['status' => 'unresolved']);
            });

        return [
            'processed' => $processed,
            'resolved' => $resolved,
            'candidates' => $candidates,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function generateDomainCandidates(string $name): array
    {
        $name = strtolower(trim($name));
        if ($name === '') {
            return [];
        }

        $name = preg_replace('/\b(pty|ltd|proprietary|limited|the)\b/i', ' ', $name) ?? $name;
        $name = preg_replace('/[^a-z0-9 ]+/', ' ', $name) ?? $name;
        $parts = array_values(array_filter(explode(' ', $name), fn ($p) => strlen($p) > 1));

        if (count($parts) === 0) {
            return [];
        }

        $base = implode('', array_slice($parts, 0, 3));

        $candidates = [
            $base.'.com.au',
            $base.'.com',
            implode('-', array_slice($parts, 0, 3)).'.com.au',
        ];

        return array_values(array_unique(array_filter($candidates)));
    }

    /**
     * @return array{reachable:bool,http_status:int,url:string}
     */
    private function probeDomain(string $domain): array
    {
        $urls = [
            'https://'.$domain,
            'http://'.$domain,
        ];

        foreach ($urls as $url) {
            try {
                $response = Http::retry(1, 500)
                    ->timeout(10)
                    ->withHeaders(['User-Agent' => 'Doughnut-Domain-Discovery/1.0'])
                    ->head($url);

                if ($response->successful()) {
                    return ['reachable' => true, 'http_status' => $response->status(), 'url' => $url];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return ['reachable' => false, 'http_status' => 0, 'url' => 'https://'.$domain];
    }
}
