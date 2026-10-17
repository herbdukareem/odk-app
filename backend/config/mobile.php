<?php

return [
    'manifest_path' => env('MOBILE_APP_RELEASE_MANIFEST_PATH', public_path('downloads/datakit-qa-update.json')),
    'fallback' => [
        'app_id' => 'ng.gov.nicare.qa',
        'version' => '0.1.2',
        'version_code' => 3,
        'download_url' => 'https://datakit.pro/downloads/datakit-qa.apk',
        'mandatory' => false,
        'release_notes' => 'Improved mobile syncing and app stability.',
        'checksum_sha256' => null,
    ],
];
