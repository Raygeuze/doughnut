<?php

return [
    'policy_version' => env('OUTREACH_POLICY_VERSION', 'v1'),
    'cooldown_days' => (int) env('OUTREACH_COOLDOWN_DAYS', 30),
    'domain_suppression_propagation_enabled' => (bool) env('OUTREACH_DOMAIN_SUPPRESSION_PROPAGATION_ENABLED', true),
    'renewal_window' => [
        'enabled' => (bool) env('OUTREACH_RENEWAL_WINDOW_ENABLED', true),
        'lead_days' => (int) env('OUTREACH_RENEWAL_WINDOW_LEAD_DAYS', 30),
        'lag_days' => (int) env('OUTREACH_RENEWAL_WINDOW_LAG_DAYS', 14),
        'allow_without_signal' => (bool) env('OUTREACH_RENEWAL_WINDOW_ALLOW_WITHOUT_SIGNAL', false),
    ],
    'domain_winner' => [
        'role_scores' => [
            'accounts' => 100,
            'accountspayable' => 100,
            'billing' => 80,
            'finance' => 80,
            'admin' => 80,
            'office' => 80,
            'info' => 60,
            'contact' => 60,
            'enquiries' => 60,
            'enquiry' => 60,
        ],
        'named_mailbox_score' => 40,
    ],
    'sources' => [
        'abr' => [
            'base_url' => env('OUTREACH_ABR_BASE_URL', 'https://abr.business.gov.au/abrxmlsearch/AbrXmlSearch.asmx'),
            'authentication_guid' => env('ABR_AUTHENTICATION_GUID', ''),
        ],
        'data_gov_au' => [
            'base_url' => env('OUTREACH_DATAGOV_BASE_URL', 'https://data.gov.au/data/api/3/action'),
        ],
    ],
];
