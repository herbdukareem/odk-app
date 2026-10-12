<?php

namespace App\Contracts;

interface BillingPaymentGatewayInterface
{
    public function key(): string;

    /** @param array<string, mixed> $payload */
    public function createCheckout(array $payload): array;

    /** @return array<string, mixed> */
    public function verify(string $reference): array;

    public function verifyWebhookSignature(string $payload, ?string $signature): bool;

    /** @param array<string, mixed> $payload */
    public function normalizeWebhookEvent(array $payload): ?array;
}
