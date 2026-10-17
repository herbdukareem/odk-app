<?php

namespace Tests\Feature;

use Tests\TestCase;

class MobileAppVersionTest extends TestCase
{
    public function test_mobile_app_version_manifest_is_public(): void
    {
        config([
            'mobile.manifest_path' => storage_path('framework/testing/missing-mobile-app-manifest.json'),
            'mobile.fallback' => [
                'app_id' => 'ng.gov.nicare.qa',
                'version' => '0.1.2',
                'version_code' => 3,
                'download_url' => 'https://datakit.pro/downloads/datakit-qa.apk',
                'mandatory' => false,
                'release_notes' => 'Improved mobile syncing.',
                'checksum_sha256' => null,
            ],
        ]);

        $this->getJson('/api/mobile/app-version')
            ->assertOk()
            ->assertJsonPath('data.app_id', 'ng.gov.nicare.qa')
            ->assertJsonPath('data.version', '0.1.2')
            ->assertJsonPath('data.version_code', 3)
            ->assertJsonPath('data.download_url', 'https://datakit.pro/downloads/datakit-qa.apk')
            ->assertJsonPath('data.mandatory', false);
    }
}
