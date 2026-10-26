<?php

namespace App\Console\Commands;

use App\Models\Lead;
use App\Models\LeadSource;
use App\Services\Outreach\EligibilityService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OutreachIngestLeadCommand extends Command
{
    protected $signature = 'outreach:ingest-lead
        {email : Lead email address}
        {source_url : Public URL where the email was found}
        {--target_domain= : Target business domain for ownership relevance}
        {--target_name= : Optional business name}
        {--visibility=public : public|authenticated|paywalled|blocked|unknown}
        {--http_status=200 : HTTP status observed for source URL}
        {--snippet= : Page text snippet around the email}
        {--context= : Optional context text around the email}';

    protected $description = 'Ingest a lead + evidence and run eligibility classification.';

    public function handle(EligibilityService $eligibility): int
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $sourceUrl = trim((string) $this->argument('source_url'));

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email syntax.');
            return self::FAILURE;
        }

        $lead = Lead::query()->firstOrCreate(
            ['email' => $email],
            [
                'target_business_name' => $this->option('target_name') ?: null,
                'target_business_domain' => $this->option('target_domain') ?: null,
                'first_seen_at' => CarbonImmutable::now(),
            ],
        );

        $host = parse_url($sourceUrl, PHP_URL_HOST);
        $sourceDomain = is_string($host) ? strtolower($host) : '';

        $evidencePayload = [
            $sourceUrl,
            $sourceDomain,
            (string) $this->option('http_status'),
            (string) $this->option('visibility'),
            (string) ($this->option('snippet') ?: ''),
            (string) ($this->option('context') ?: ''),
        ];

        LeadSource::query()->create([
            'lead_id' => $lead->id,
            'source_url' => $sourceUrl,
            'source_domain' => $sourceDomain,
            'source_retrieved_at' => CarbonImmutable::now(),
            'http_status' => (int) $this->option('http_status'),
            'crawl_visibility' => (string) $this->option('visibility'),
            'page_title' => null,
            'page_text_snippet' => (string) ($this->option('snippet') ?: ''),
            'email_found_context' => (string) ($this->option('context') ?: ''),
            'evidence_hash' => hash('sha256', json_encode($evidencePayload)),
        ]);

        if ($this->option('target_name')) {
            $lead->target_business_name = (string) $this->option('target_name');
        }
        if ($this->option('target_domain')) {
            $lead->target_business_domain = strtolower((string) $this->option('target_domain'));
        }
        $lead->save();

        $result = $eligibility->evaluateLead($lead->fresh());

        $this->info(sprintf(
            'Lead %d %s -> %s [%s]',
            $lead->id,
            $lead->email,
            $result['status'],
            implode(',', $result['reasons']),
        ));

        return self::SUCCESS;
    }
}
