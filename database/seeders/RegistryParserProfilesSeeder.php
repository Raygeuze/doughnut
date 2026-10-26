<?php

namespace Database\Seeders;

use App\Models\RegistryParserProfile;
use Illuminate\Database\Seeder;

class RegistryParserProfilesSeeder extends Seeder
{
    /**
     * Seed default parser profiles for dataset materialization.
     */
    public function run(): void
    {
        RegistryParserProfile::query()->updateOrCreate(
            ['profile_key' => 'abn_csv_v1'],
            [
                'source_system' => 'data_gov_au_dataset',
                'format' => 'CSV',
                'enabled' => true,
                'mapping' => [
                    'source_record_id' => [
                        'ABN',
                        'abn',
                        'source_record_id',
                    ],
                    'business_name' => [
                        'Legal Name',
                        'legal_name',
                        'Entity Name',
                        'entity_name',
                        'Business Name',
                        'business_name',
                        'Name',
                        'name',
                    ],
                    'abn' => [
                        'ABN',
                        'abn',
                    ],
                    'state' => [
                        'State',
                        'state',
                        'State Code',
                        'state_code',
                    ],
                    'postcode' => [
                        'Postcode',
                        'postcode',
                        'Post Code',
                        'post_code',
                    ],
                ],
                'notes' => 'Generic ABN-oriented CSV mapping profile for initial Stage 1 materialization.',
            ],
        );
    }
}
