<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;

class MobileAppVersionController
{
    public function __invoke(): JsonResponse
    {
        $payload = config('mobile.fallback', []);
        $manifestPath = (string) config('mobile.manifest_path');

        if (is_file($manifestPath)) {
            $manifest = json_decode((string) file_get_contents($manifestPath), true);

            if (is_array($manifest)) {
                $payload = array_replace($payload, $manifest);
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'app_id' => (string) ($payload['app_id'] ?? ''),
                'version' => (string) ($payload['version'] ?? ''),
                'version_code' => (int) ($payload['version_code'] ?? 0),
                'download_url' => (string) ($payload['download_url'] ?? ''),
                'mandatory' => (bool) ($payload['mandatory'] ?? false),
                'release_notes' => $payload['release_notes'] ?? null,
                'checksum_sha256' => $payload['checksum_sha256'] ?? null,
            ],
        ]);
    }
}
