<?php

namespace App\Services\Billing;

use App\Contracts\BillingPaymentGatewayInterface;
use InvalidArgumentException;

class BillingGatewayManager
{
    /** @var array<string, BillingPaymentGatewayInterface> */
    private array $gateways = [];

    public function __construct(iterable $gateways = [])
    {
        foreach ($gateways as $gateway) {
            $this->register($gateway);
        }
    }

    public function register(BillingPaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->key()] = $gateway;
    }

    public function driver(?string $key = null): BillingPaymentGatewayInterface
    {
        $key ??= (string) config('billing.default', 'paystack');
        $gateway = $this->gateways[$key] ?? null;

        if (!$gateway) {
            throw new InvalidArgumentException("Billing gateway [{$key}] is not configured.");
        }

        return $gateway;
    }
}
