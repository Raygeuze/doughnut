<?php

namespace App\Console\Commands;

use App\Models\RegistryDataset;
use App\Services\Outreach\Sources\DataGovAuClient;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class OutreachDiscoverDatasetsCommand extends Command
{
    protected $signature = 'outreach:discover-datasets
        {query=abn : Search query for data.gov.au package_search}
        {--rows=25 : Number of rows to fetch}
        {--dry-run : Do not persist results}';

    protected $description = 'Discover relevant public datasets from data.gov.au and optionally persist them.';

    public function handle(DataGovAuClient $client): int
    {
        $query = (string) $this->argument('query');
        $rows = max(1, (int) $this->option('rows'));
        $dryRun = (bool) $this->option('dry-run');

        $datasets = $client->searchDatasets($query, $rows);
        $discoveredAt = CarbonImmutable::now();

        $created = 0;
        $updated = 0;

        foreach ($datasets as $dataset) {
            $attributes = [
                'portal' => 'data.gov.au',
                'dataset_id' => (string) ($dataset['id'] ?? ''),
            ];

            if ($attributes['dataset_id'] === '') {
                continue;
            }

            $values = [
                'name' => $dataset['name'] ?? null,
                'title' => $dataset['title'] ?? null,
                'organization_name' => $dataset['organization']['title'] ?? null,
                'source_url' => $dataset['url'] ?? ($dataset['resources'][0]['url'] ?? null),
                'license_id' => $dataset['license_id'] ?? null,
                'is_open' => (bool) ($dataset['isopen'] ?? false),
                'num_resources' => (int) ($dataset['num_resources'] ?? 0),
                'raw_payload' => $dataset,
                'discovered_at' => $discoveredAt,
            ];

            if ($dryRun) {
                continue;
            }

            $existing = RegistryDataset::query()->where($attributes)->first();
            RegistryDataset::query()->updateOrCreate($attributes, $values);

            if ($existing) {
                $updated++;
            } else {
                $created++;
            }
        }

        $this->info(sprintf(
            'Discovered %d dataset(s). %s',
            count($datasets),
            $dryRun ? 'Dry run only, no records persisted.' : "created={$created} updated={$updated}",
        ));

        return self::SUCCESS;
    }
}
