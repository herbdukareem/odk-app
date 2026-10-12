<?php

namespace App\Services\Billing;

use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class BillingWebhookService
{
    public function __construct(private readonly BillingGatewayManager $gateways)
    {
    }

    /** @return array<string, mixed>|null */
    public function normalize(string $provider, string $payload, ?string $signature): ?array
    {
        $gateway = $this->gateways->driver($provider);

        if (!$gateway->verifyWebhookSignature($payload, $signature)) {
            throw new UnauthorizedHttpException('Webhook signature verification failed.');
        }

        $decoded = json_decode($payload, true);
        if (!is_array($decoded)) {
            throw new UnauthorizedHttpException('Webhook payload is invalid.');
        }

        return $gateway->normalizeWebhookEvent($decoded);
    }
}
