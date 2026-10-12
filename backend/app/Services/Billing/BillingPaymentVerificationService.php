<?php

namespace App\Services\Billing;

class BillingPaymentVerificationService
{
    public function __construct(private readonly BillingGatewayManager $gateways)
    {
    }

    /** @return array<string, mixed> */
    public function verify(string $reference, ?string $provider = null): array
    {
        return $this->gateways->driver($provider)->verify($reference);
    }
}
