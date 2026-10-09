<?php

namespace App\Services\Integration;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NicareApiClient
{
    public function getEndpoint(string $key): string
    {
        return (string) config("services.nicare.endpoints.{$key}");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCollection(string $endpointKey, array $query = []): array
    {
        $endpoint = $this->getEndpoint($endpointKey);
        $items = [];
        $page = 1;

        do {
            $payload = $this->get($endpoint, $query + ['page' => $page, 'per_page' => $query['per_page'] ?? 500]);
            $pageItems = $this->extractItems($payload);
            $items = array_merge($items, $pageItems);

            $meta = $this->extractMeta($payload);
            $lastPage = (int) ($meta['last_page'] ?? $page);
            $page += 1;
        } while ($page <= $lastPage);

        return $items;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(string $endpointKey, array $query = []): array
    {
        return $this->get($this->getEndpoint($endpointKey), $query);
    }

    /**
     * @return array<string, mixed>
     */
    private function get(string $endpoint, array $query = []): array
    {
        $baseUrl = rtrim((string) config('services.nicare.base_url'), '/');
        if ($baseUrl === '') {
            throw new RuntimeException('NICARE_API_BASE_URL is not configured.');
        }

        $response = $this->http()
            ->acceptJson()
            ->get($baseUrl.'/'.ltrim($endpoint, '/'), $query);

        if ($response->failed()) {
            throw new RuntimeException(sprintf(
                'NICARE request failed for [%s] with HTTP %s: %s',
                $endpoint,
                $response->status(),
                $response->body()
            ));
        }

        return $response->json() ?? [];
    }

    private function http(): PendingRequest
    {
        $request = Http::timeout((int) config('services.nicare.timeout', 30));
        $token = trim((string) config('services.nicare.token'));

        return $token !== '' ? $request->withToken($token) : $request;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function extractItems(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        if (isset($data['data']) && is_array($data['data'])) {
            return array_values($data['data']);
        }

        if (array_is_list($data)) {
            return array_values($data);
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractMeta(array $payload): array
    {
        $data = $payload['data'] ?? [];

        if (isset($data['meta']) && is_array($data['meta'])) {
            return $data['meta'];
        }

        if (isset($payload['meta']) && is_array($payload['meta'])) {
            return $payload['meta'];
        }

        if (is_array($data) && isset($data['last_page'])) {
            return $data;
        }

        return [];
    }
}
