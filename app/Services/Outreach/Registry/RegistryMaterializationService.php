<?php

namespace App\Services\Outreach\Registry;

use App\Models\RegistryIngestJob;
use App\Models\RegistrySeedBatch;
use App\Models\RegistrySeedBusiness;
use App\Services\Outreach\AuditLogger;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

class RegistryMaterializationService
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    /**
     * @return array{rows_read:int,created:int,updated:int,rejected:int}
     */
    public function materializeCsvJob(RegistryIngestJob $job, int $rowLimit = 0, bool $dryRun = false): array
    {
        $profile = $job->parserProfile;
        if (! $profile || ! is_array($profile->mapping)) {
            throw new \RuntimeException('Parser profile mapping is missing for ingest job.');
        }

        $job->update([
            'status' => 'running',
            'started_at' => CarbonImmutable::now(),
            'error_message' => null,
        ]);

        $mapping = $profile->mapping;

        $dir = 'outreach-imports';
        Storage::disk('local')->makeDirectory($dir);
        $tempPath = $dir.'/'.sha1($job->source_url.'|'.microtime(true)).'.csv';
        $absolute = Storage::disk('local')->path($tempPath);

        $response = Http::withHeaders([
            'User-Agent' => 'Doughnut-Outreach-Importer/1.0',
            'Connection' => 'close',
        ])
            ->retry(4, 2000)
            ->withOptions([
                'sink' => $absolute,
                'version' => 1.1,
                'connect_timeout' => 20,
                'read_timeout' => 120,
            ])
            ->timeout(300)
            ->get($job->source_url);

        if (! $response->successful()) {
            throw new \RuntimeException("Failed to download source URL ({$response->status()}).");
        }

        $handle = fopen($absolute, 'rb');

        if (! $handle) {
            Storage::disk('local')->delete($tempPath);
            throw new \RuntimeException('Failed to open downloaded CSV file for parsing.');
        }

        $headers = fgetcsv($handle);
        if (! is_array($headers)) {
            fclose($handle);
            Storage::disk('local')->delete($tempPath);
            throw new \RuntimeException('CSV does not contain a valid header row.');
        }

        $headerIndex = $this->normalizeHeaderIndex($headers);

        $batch = null;
        if (! $dryRun) {
            $batch = RegistrySeedBatch::query()->create([
                'source_system' => $job->source_system,
                'query_type' => 'dataset_resource',
                'query_value' => $job->source_identifier,
                'query_metadata' => [
                    'ingest_job_id' => $job->id,
                    'parser_profile' => $profile->profile_key,
                ],
                'results_count' => 0,
                'retrieved_at' => CarbonImmutable::now(),
            ]);
        }

        $rowsRead = 0;
        $created = 0;
        $updated = 0;
        $rejected = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (! is_array($row)) {
                continue;
            }

            $rowsRead++;
            if ($rowLimit > 0 && $rowsRead > $rowLimit) {
                break;
            }

            $rowAssoc = $this->associateRow($headers, $row);

            $businessName = $this->pickFirstMappedValue($mapping['business_name'] ?? [], $rowAssoc, $headerIndex);
            $abnRaw = $this->pickFirstMappedValue($mapping['abn'] ?? [], $rowAssoc, $headerIndex);
            $state = $this->pickFirstMappedValue($mapping['state'] ?? [], $rowAssoc, $headerIndex);
            $postcode = $this->pickFirstMappedValue($mapping['postcode'] ?? [], $rowAssoc, $headerIndex);
            $sourceRecord = $this->pickFirstMappedValue($mapping['source_record_id'] ?? [], $rowAssoc, $headerIndex);

            $abn = is_string($abnRaw) ? preg_replace('/\D+/', '', $abnRaw) : null;
            $sourceRecordId = $sourceRecord ?: ($abn ?: sha1(json_encode($rowAssoc)));

            if (! is_string($businessName) || trim($businessName) === '') {
                $rejected++;
                continue;
            }

            $attributes = [
                'source_system' => $job->source_system,
                'source_record_id' => (string) $sourceRecordId,
            ];

            $values = [
                'registry_seed_batch_id' => $batch?->id,
                'business_name' => trim($businessName),
                'abn' => is_string($abn) && $abn !== '' ? $abn : null,
                'state' => is_string($state) && trim($state) !== '' ? trim($state) : null,
                'postcode' => is_string($postcode) && trim($postcode) !== '' ? trim($postcode) : null,
                'source_url' => $job->source_url,
                'source_retrieved_at' => CarbonImmutable::now(),
                'status' => 'pending',
                'raw_payload' => $rowAssoc,
            ];

            if ($dryRun) {
                continue;
            }

            $existing = RegistrySeedBusiness::query()->where($attributes)->first();
            $saved = RegistrySeedBusiness::query()->updateOrCreate($attributes, $values);

            $this->audit->log(
                entityType: 'registry_seed_business',
                entityId: $saved->id,
                action: $existing ? 'registry_seed_updated_from_dataset' : 'registry_seed_created_from_dataset',
                sourceSystem: $job->source_system,
                sourceIdentifier: $job->source_identifier,
                payload: [
                    'ingest_job_id' => $job->id,
                    'parser_profile' => $profile->profile_key,
                    'transforms' => [
                        'abn_stripped_non_digits' => true,
                        'business_name_trimmed' => true,
                        'state_trimmed_nullable' => true,
                        'postcode_trimmed_nullable' => true,
                    ],
                    'mapped_headers' => [
                        'source_record_id' => $mapping['source_record_id'] ?? [],
                        'business_name' => $mapping['business_name'] ?? [],
                        'abn' => $mapping['abn'] ?? [],
                        'state' => $mapping['state'] ?? [],
                        'postcode' => $mapping['postcode'] ?? [],
                    ],
                    'abn' => $values['abn'],
                    'business_name' => $values['business_name'],
                ],
            );

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        fclose($handle);
        Storage::disk('local')->delete($tempPath);

        if (! $dryRun && $batch) {
            $batch->update(['results_count' => $created + $updated]);
        }

        $job->update([
            'status' => $dryRun ? 'pending' : 'completed',
            'rows_read' => $rowsRead,
            'rows_created' => $created,
            'rows_updated' => $updated,
            'rows_rejected' => $rejected,
            'finished_at' => CarbonImmutable::now(),
        ]);

        return [
            'rows_read' => $rowsRead,
            'created' => $created,
            'updated' => $updated,
            'rejected' => $rejected,
        ];
    }

    /**
     * @param array<int, string> $headers
     * @return array<string, int>
     */
    private function normalizeHeaderIndex(array $headers): array
    {
        $index = [];

        foreach ($headers as $position => $header) {
            $normalized = $this->normalizeHeader((string) $header);
            $index[$normalized] = $position;
        }

        return $index;
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    private function associateRow(array $headers, array $row): array
    {
        $assoc = [];

        foreach ($headers as $i => $header) {
            $assoc[(string) $header] = $row[$i] ?? null;
        }

        return $assoc;
    }

    /**
     * @param mixed $candidateHeaders
     * @param array<string, string|null> $rowAssoc
     * @param array<string, int> $headerIndex
     */
    private function pickFirstMappedValue(mixed $candidateHeaders, array $rowAssoc, array $headerIndex): ?string
    {
        if (! is_array($candidateHeaders)) {
            return null;
        }

        foreach ($candidateHeaders as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $normalized = $this->normalizeHeader($candidate);
            if (! array_key_exists($normalized, $headerIndex)) {
                continue;
            }

            $header = array_keys($rowAssoc)[$headerIndex[$normalized]] ?? null;
            if (! is_string($header)) {
                continue;
            }

            $value = $rowAssoc[$header] ?? null;
            if (! is_string($value)) {
                continue;
            }

            $trimmed = trim($value);
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return null;
    }

    private function normalizeHeader(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $value) ?? $value, '_'));
    }
}
