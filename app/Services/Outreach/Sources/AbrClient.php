<?php

namespace App\Services\Outreach\Sources;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;

class AbrClient
{
    /**
     * @return array{retrieved_at:CarbonImmutable, records:array<int,array<string,mixed>>,raw_xml:string}
     */
    public function searchByPostcode(string $postcode): array
    {
        return $this->request('SearchByPostcode', [
            'postcode' => $postcode,
        ]);
    }

    /**
     * @return array{retrieved_at:CarbonImmutable, records:array<int,array<string,mixed>>,raw_xml:string}
     */
    public function searchByName(string $name, ?string $postcode = null): array
    {
        return $this->request('ABRSearchByNameSimpleProtocol', [
            'name' => $name,
            'postcode' => $postcode ?? '',
            'legalName' => 'Y',
            'tradingName' => 'Y',
            'NSW' => 'Y',
            'SA' => 'Y',
            'ACT' => 'Y',
            'VIC' => 'Y',
            'WA' => 'Y',
            'NT' => 'Y',
            'QLD' => 'Y',
            'TAS' => 'Y',
        ]);
    }

    /**
     * @param array<string, string> $params
     * @return array{retrieved_at:CarbonImmutable, records:array<int,array<string,mixed>>,raw_xml:string}
     */
    private function request(string $operation, array $params): array
    {
        $guid = trim((string) config('outreach.sources.abr.authentication_guid'));
        if ($guid === '') {
            throw new RuntimeException('ABR authentication GUID is missing. Set ABR_AUTHENTICATION_GUID in environment.');
        }

        $baseUrl = rtrim((string) config('outreach.sources.abr.base_url'), '/');
        $response = Http::timeout(40)
            ->accept('application/xml,text/xml')
            ->get("{$baseUrl}/{$operation}", array_merge($params, [
                'authenticationGuid' => $guid,
            ]));

        if (! $response->successful()) {
            throw new RuntimeException("ABR request failed with HTTP {$response->status()}.");
        }

        $xmlString = (string) $response->body();
        $records = $this->extractRecords($xmlString);

        return [
            'retrieved_at' => CarbonImmutable::now(),
            'records' => $records,
            'raw_xml' => $xmlString,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractRecords(string $xmlString): array
    {
        $xml = @simplexml_load_string($xmlString);
        if (! $xml instanceof SimpleXMLElement) {
            return [];
        }

        $records = [];
        $nodes = $xml->xpath('//*[local-name()="searchResultsRecord"]') ?: [];

        foreach ($nodes as $node) {
            $recordXml = $node->asXML() ?: '';
            $recordAbn = $this->xpathFirst((SimpleXMLElement) $node, './/*[local-name()="identifierValue"]');
            $recordName = $this->xpathFirst((SimpleXMLElement) $node, './/*[local-name()="organisationName"]')
                ?? $this->xpathFirst((SimpleXMLElement) $node, './/*[local-name()="fullName"]');
            $recordState = $this->xpathFirst((SimpleXMLElement) $node, './/*[local-name()="stateCode"]');
            $recordPostcode = $this->xpathFirst((SimpleXMLElement) $node, './/*[local-name()="postcode"]');

            $records[] = [
                'abn' => $recordAbn,
                'business_name' => $recordName,
                'state' => $recordState,
                'postcode' => $recordPostcode,
                'raw_record_xml' => $recordXml,
            ];
        }

        return $records;
    }

    private function xpathFirst(SimpleXMLElement $node, string $query): ?string
    {
        $result = $node->xpath($query);

        if (! is_array($result) || ! isset($result[0])) {
            return null;
        }

        $value = trim((string) $result[0]);

        return $value === '' ? null : $value;
    }
}
