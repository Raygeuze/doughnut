<?php

namespace App\Services\Outreach\Sources;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DataGovAuClient
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function searchDatasets(string $query, int $rows = 25): array
    {
        $baseUrl = rtrim((string) config('outreach.sources.data_gov_au.base_url'), '/');

        $response = Http::timeout(30)
            ->acceptJson()
            ->get("{$baseUrl}/package_search", [
                'q' => $query,
                'rows' => $rows,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to query data.gov.au package_search API.');
        }

        $payload = $response->json();
        if (! Arr::get($payload, 'success')) {
            throw new RuntimeException('data.gov.au API returned an unsuccessful response payload.');
        }

        $results = Arr::get($payload, 'result.results', []);

        return is_array($results) ? $results : [];
    }
}
