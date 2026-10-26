<?php

namespace App\Services\Outreach\Registry;

use App\Models\RegistryDataset;
use App\Models\RegistryDatasetResource;
use Carbon\CarbonImmutable;

class DatasetResourceDiscoveryService
{
    /**
     * @return array{created:int,updated:int,total:int}
     */
    public function discover(?int $datasetId = null, bool $dryRun = false): array
    {
        $query = RegistryDataset::query();

        if ($datasetId !== null) {
            $query->whereKey($datasetId);
        }

        $created = 0;
        $updated = 0;
        $total = 0;

        $query->chunkById(100, function ($datasets) use (&$created, &$updated, &$total, $dryRun): void {
            foreach ($datasets as $dataset) {
                $resources = $dataset->raw_payload['resources'] ?? [];
                if (! is_array($resources)) {
                    continue;
                }

                foreach ($resources as $resource) {
                    if (! is_array($resource)) {
                        continue;
                    }

                    $resourceId = (string) ($resource['id'] ?? '');
                    $url = (string) ($resource['url'] ?? '');

                    if ($resourceId === '' || $url === '') {
                        continue;
                    }

                    $total++;

                    $values = [
                        'name' => $resource['name'] ?? null,
                        'format' => isset($resource['format']) ? strtoupper((string) $resource['format']) : null,
                        'state' => $resource['state'] ?? null,
                        'url' => $url,
                        'size' => isset($resource['size']) && is_numeric($resource['size'])
                            ? (int) $resource['size']
                            : null,
                        'last_modified' => $this->parseDate($resource['last_modified'] ?? null),
                        'raw_payload' => $resource,
                        'discovered_at' => CarbonImmutable::now(),
                    ];

                    if ($dryRun) {
                        continue;
                    }

                    $attributes = [
                        'registry_dataset_id' => $dataset->id,
                        'resource_id' => $resourceId,
                    ];

                    $existing = RegistryDatasetResource::query()->where($attributes)->first();
                    RegistryDatasetResource::query()->updateOrCreate($attributes, $values);

                    if ($existing) {
                        $updated++;
                    } else {
                        $created++;
                    }
                }
            }
        });

        return [
            'created' => $created,
            'updated' => $updated,
            'total' => $total,
        ];
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
