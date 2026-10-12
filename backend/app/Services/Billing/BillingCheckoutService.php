<?php

namespace App\Services\Billing;

use App\Contracts\BillingPaymentGatewayInterface;

class BillingCheckoutService
{
    public function __construct(private readonly BillingGatewayManager $gateways)
    {
    }

    /** @param array<string, mixed> $payload */
    public function create(array $payload, ?string $provider = null): array
    {
        return $this->gateways->driver($provider)->createCheckout($payload);
    }
}
