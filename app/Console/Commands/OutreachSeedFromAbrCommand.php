<?php

namespace App\Console\Commands;

use App\Models\RegistrySeedBatch;
use App\Models\RegistrySeedBusiness;
use App\Services\Outreach\AuditLogger;
use App\Services\Outreach\Sources\AbrClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OutreachSeedFromAbrCommand extends Command
{
    protected $signature = 'outreach:seed-from-abr
        {--postcode= : Postcode query for ABR SearchByPostcode}
        {--name= : Name query for ABRSearchByNameSimpleProtocol}
        {--name-postcode= : Optional postcode filter used with --name}
        {--dry-run : Do not persist results}';

    protected $description = 'Call ABR web service and persist seed business candidates for Stage 1.';

    public function handle(AbrClient $abrClient, AuditLogger $audit): int
    {
        $postcode = trim((string) $this->option('postcode'));
        $name = trim((string) $this->option('name'));
        $namePostcode = trim((string) $this->option('name-postcode'));
        $dryRun = (bool) $this->option('dry-run');

        if ($postcode === '' && $name === '') {
            $this->error('Provide either --postcode or --name.');
            return self::FAILURE;
        }

        if ($postcode !== '' && $name !== '') {
            $this->error('Use either --postcode or --name, not both.');
            return self::FAILURE;
        }

        $queryType = $postcode !== '' ? 'postcode' : 'name';
        $queryValue = $postcode !== '' ? $postcode : $name;

        $result = $postcode !== ''
            ? $abrClient->searchByPostcode($postcode)
            : $abrClient->searchByName($name, $namePostcode !== '' ? $namePostcode : null);

        $retrievedAt = $result['retrieved_at'];
        $records = $result['records'];

        $batch = null;
        if (! $dryRun) {
            $batch = RegistrySeedBatch::query()->create([
                'source_system' => 'abr_webservice',
                'query_type' => $queryType,
                'query_value' => $queryValue,
                'query_metadata' => [
                    'name_postcode' => $namePostcode !== '' ? $namePostcode : null,
                ],
                'results_count' => count($records),
                'retrieved_at' => $retrievedAt,
            ]);
        }

        $created = 0;
        $updated = 0;

        foreach ($records as $record) {
            $abn = isset($record['abn']) && is_string($record['abn'])
                ? preg_replace('/\D+/', '', $record['abn'])
                : null;
            $sourceRecordId = $abn ?: sha1((string) ($record['raw_record_xml'] ?? json_encode($record)));

            $attributes = [
                'source_system' => 'abr_webservice',
                'source_record_id' => $sourceRecordId,
            ];

            $values = [
                'registry_seed_batch_id' => $batch?->id,
                'business_name' => $record['business_name'] ?? null,
                'abn' => $abn,
                'state' => $record['state'] ?? null,
                'postcode' => $record['postcode'] ?? null,
                'source_url' => 'https://abr.business.gov.au/abrxmlsearch/AbrXmlSearch.asmx',
                'source_retrieved_at' => $retrievedAt,
                'status' => 'pending',
                'raw_payload' => $record,
            ];

            if ($dryRun) {
                continue;
            }

            $existing = RegistrySeedBusiness::query()->where($attributes)->first();
            $saved = RegistrySeedBusiness::query()->updateOrCreate($attributes, $values);

            $audit->log(
                entityType: 'registry_seed_business',
                entityId: $saved->id,
                action: $existing ? 'registry_seed_updated_from_abr' : 'registry_seed_created_from_abr',
                sourceSystem: 'abr_webservice',
                sourceIdentifier: $sourceRecordId,
                payload: [
                    'abn' => $abn,
                    'business_name' => $values['business_name'],
                    'state' => $values['state'],
                    'postcode' => $values['postcode'],
                    'batch_id' => $batch?->id,
                ],
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        $this->info(sprintf(
            'ABR query complete. records=%d %s',
            count($records),
            $dryRun ? 'Dry run only, no records persisted.' : "batch_id={$batch?->id} created={$created} updated={$updated}",
        ));

        if (count($records) === 0) {
            $this->warn('No records parsed from ABR response. This can occur for empty results or if response schema changes.');
        }

        return self::SUCCESS;
    }
}
