<?php

return [
    'nicare' => [
        'base_url' => env('NICARE_API_BASE_URL'),
        'token' => env('NICARE_API_TOKEN'),
        'reference_sync_enabled' => env('NICARE_REFERENCE_SYNC_ENABLED', false),
        'timeout' => env('NICARE_API_TIMEOUT', 30),
        'endpoints' => [
            'facilities' => env('NICARE_FACILITIES_ENDPOINT', '/api/odk/reference/facilities'),
            'lgas' => env('NICARE_LGAS_ENDPOINT', '/api/odk/reference/lgas'),
            'wards' => env('NICARE_WARDS_ENDPOINT', '/api/odk/reference/wards'),
            'premium_metadata' => env('NICARE_PREMIUM_METADATA_ENDPOINT', '/api/odk/reference/premium-metadata'),
        ],
    ],
];
