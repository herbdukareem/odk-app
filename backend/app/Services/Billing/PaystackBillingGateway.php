<?php

namespace App\Services\Billing;

use App\Contracts\BillingPaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PaystackBillingGateway implements BillingPaymentGatewayInterface
{
    public function key(): string
    {
        return 'paystack';
    }

    public function createCheckout(array $payload): array
    {
        $secretKey = config('billing.gateways.paystack.secret_key');

        if (!is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('Paystack billing is not configured. Set PAYSTACK_SECRET_KEY.');
        }

        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->post(rtrim((string) config('billing.gateways.paystack.base_url'), '/') . '/transaction/initialize', $payload);

        if ($response->failed()) {
            throw new RuntimeException('The billing provider could not initialize checkout.');
        }

        $data = $response->json('data', []);

        return [
            'provider' => $this->key(),
            'reference' => $data['reference'] ?? null,
            'authorization_url' => $data['authorization_url'] ?? null,
            'access_code' => $data['access_code'] ?? null,
        ];
    }

    public function verify(string $reference): array
    {
        $secretKey = config('billing.gateways.paystack.secret_key');

        if (!is_string($secretKey) || $secretKey === '') {
            throw new RuntimeException('Paystack billing is not configured. Set PAYSTACK_SECRET_KEY.');
        }

        $response = Http::withToken($secretKey)
            ->acceptJson()
            ->get(rtrim((string) config('billing.gateways.paystack.base_url'), '/') . '/transaction/verify/' . urlencode($reference));

        if ($response->failed()) {
            throw new RuntimeException('The billing provider could not verify this transaction.');
        }

        return [
            'provider' => $this->key(),
            'reference' => $reference,
            'status' => $response->json('data.status'),
            'amount' => $response->json('data.amount'),
            'currency' => $response->json('data.currency'),
            'subscription_reference' => $response->json('data.subscription.subscription_code')
                ?? $response->json('data.subscription_code'),
            'customer_code' => $response->json('data.customer.customer_code')
                ?? $response->json('data.customer_code'),
            'authorization_code' => $response->json('data.authorization.authorization_code')
                ?? $response->json('data.authorization_code'),
            'plan_code' => $response->json('data.plan_object.plan_code')
                ?? $response->json('data.plan'),
            'raw' => $response->json('data', []),
        ];
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        $secret = config('billing.gateways.paystack.webhook_secret')
            ?: config('billing.gateways.paystack.secret_key');

        return is_string($secret)
            && $secret !== ''
            && is_string($signature)
            && hash_equals(hash_hmac('sha512', $payload, $secret), $signature);
    }

    public function normalizeWebhookEvent(array $payload): ?array
    {
        $event = (string) ($payload['event'] ?? '');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $subscriptionReference = $data['subscription_code']
            ?? data_get($data, 'subscription.subscription_code')
            ?? data_get($data, 'subscription.code');
        $reference = $data['reference']
            ?? data_get($data, 'transaction.reference')
            ?? $data['transaction_reference']
            ?? null;

        $type = match ($event) {
            'charge.success' => 'payment_succeeded',
            'invoice.payment_failed' => 'payment_failed',
            'subscription.disable' => 'subscription_canceled',
            'subscription.not_renew' => 'subscription_cancel_scheduled',
            'refund.processed' => 'payment_refunded',
            default => null,
        };

        if (!$type) {
            return null;
        }

        return [
            'type' => $type,
            'provider' => $this->key(),
            'reference' => $reference,
            'subscription_reference' => $subscriptionReference,
            'status' => $data['status'] ?? null,
            'amount' => $data['amount'] ?? data_get($data, 'transaction.amount'),
            'currency' => $data['currency'] ?? data_get($data, 'transaction.currency'),
            'plan_code' => data_get($data, 'plan_object.plan_code') ?? $data['plan'] ?? null,
            'raw' => $data,
        ];
    }
}
