<?php

namespace App\Console\Commands;

use App\Models\RegistryDatasetResource;
use App\Models\RegistryIngestJob;
use App\Models\RegistryParserProfile;
use App\Services\Outreach\Registry\DatasetResourceDiscoveryService;
use Illuminate\Console\Command;

class OutreachDiscoverResourcesCommand extends Command
{
    protected $signature = 'outreach:discover-resources
        {--dataset-id= : Optional dataset id from registry_datasets table}
        {--create-jobs : Create ingest jobs for discovered resources}
        {--profile=abn_csv_v1 : Parser profile key used when creating jobs}
        {--formats=CSV : Comma-separated formats eligible for job creation}
        {--max-size-mb=25 : Max resource size in MB when creating jobs (0 disables limit)}
        {--dry-run : Do not persist resources/jobs}';

    protected $description = 'Expand discovered datasets into resources and optional ingest jobs.';

    public function handle(DatasetResourceDiscoveryService $service): int
    {
        $datasetId = $this->option('dataset-id') ? (int) $this->option('dataset-id') : null;
        $createJobs = (bool) $this->option('create-jobs');
        $profileKey = (string) $this->option('profile');
        $dryRun = (bool) $this->option('dry-run');
        $maxSizeMb = max(0, (int) $this->option('max-size-mb'));
        $maxBytes = $maxSizeMb > 0 ? $maxSizeMb * 1024 * 1024 : 0;
        $formats = array_values(array_filter(array_map(
            static fn ($item) => strtoupper(trim((string) $item)),
            explode(',', (string) $this->option('formats')),
        )));

        $result = $service->discover($datasetId, $dryRun);

        $createdJobs = 0;

        if ($createJobs) {
            $profile = RegistryParserProfile::query()
                ->where('profile_key', $profileKey)
                ->where('enabled', true)
                ->first();

            if (! $profile) {
                $this->error("Parser profile '{$profileKey}' was not found or is disabled.");
                return self::FAILURE;
            }

            $resourceQuery = RegistryDatasetResource::query();
            if ($datasetId !== null) {
                $resourceQuery->where('registry_dataset_id', $datasetId);
            }

            $resourceQuery->chunkById(200, function ($resources) use ($profile, $formats, $dryRun, $maxBytes, &$createdJobs): void {
                foreach ($resources as $resource) {
                    $format = strtoupper((string) ($resource->format ?? ''));
                    if (! in_array($format, $formats, true)) {
                        continue;
                    }

                    if ($maxBytes > 0 && is_numeric($resource->size) && (int) $resource->size > $maxBytes) {
                        continue;
                    }

                    $attributes = [
                        'source_system' => 'data_gov_au_dataset',
                        'source_identifier' => $resource->resource_id,
                        'registry_parser_profile_id' => $profile->id,
                    ];

                    $values = [
                        'registry_dataset_resource_id' => $resource->id,
                        'source_url' => $resource->url,
                        'format' => $format,
                        'status' => 'pending',
                        'raw_payload' => [
                            'resource_name' => $resource->name,
                            'dataset_id' => $resource->registry_dataset_id,
                        ],
                    ];

                    if ($dryRun) {
                        continue;
                    }

                    RegistryIngestJob::query()->updateOrCreate($attributes, $values);
                    $createdJobs++;
                }
            });
        }

        $this->info(sprintf(
            'Resource discovery complete. resources_seen=%d created=%d updated=%d jobs=%d max_size_mb=%d %s',
            $result['total'],
            $result['created'],
            $result['updated'],
            $createdJobs,
            $maxSizeMb,
            $dryRun ? '(dry run only)' : '',
        ));

        return self::SUCCESS;
    }
}
