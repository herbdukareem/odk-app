<?php

namespace App\Services\Billing;

use App\Models\OdkBillingInvoice;
use App\Models\OdkPlan;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Models\OdkWorkspaceSubscription;
use App\Services\Saas\OdkAuditLogger;
use App\Services\Saas\OdkSubscriptionService;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class BillingSubscriptionService
{
    public function __construct(
        private readonly BillingCheckoutService $checkout,
        private readonly BillingPaymentVerificationService $verification,
        private readonly OdkSubscriptionService $subscriptions,
        private readonly OdkAuditLogger $audit,
    ) {
    }

    public function startCheckout(OdkWorkspace $workspace, OdkPlan $plan, OdkUser $actor): array
    {
        $subscription = $this->subscriptions->ensureFor($workspace);
        $currentPlan = $subscription->plan;

        if ((float) $plan->price_amount <= 0) {
            throw ValidationException::withMessages(['plan' => ['This plan is not configured for paid checkout yet.']]);
        }

        if ($currentPlan && (float) $plan->price_amount <= (float) $currentPlan->price_amount) {
            throw ValidationException::withMessages(['plan' => ['Choose a plan above your current plan.']]);
        }

        $reference = 'ODK-' . Str::upper(Str::random(20));
        $invoice = OdkBillingInvoice::create([
            'uuid' => (string) Str::uuid(),
            'workspace_id' => $workspace->id,
            'subscription_id' => $subscription->id,
            'plan_id' => $plan->id,
            'provider' => (string) config('billing.default', 'paystack'),
            'reference' => $reference,
            'amount' => $plan->price_amount,
            'currency' => $plan->currency,
            'status' => OdkBillingInvoice::STATUS_PENDING,
            'metadata' => ['actor_user_id' => $actor->id, 'plan_code' => $plan->code],
        ]);

        $providerPayload = [
            'email' => $actor->email,
            'amount' => (int) round((float) $plan->price_amount * 100),
            'currency' => $plan->currency,
            'reference' => $reference,
            'callback_url' => url('/settings?billing_reference=' . urlencode($reference)),
            'metadata' => [
                'workspace_id' => $workspace->id,
                'workspace_uuid' => $workspace->uuid,
                'invoice_uuid' => $invoice->uuid,
                'plan_code' => $plan->code,
            ],
        ];

        if ($plan->provider_plan_code) {
            $providerPayload['plan'] = $plan->provider_plan_code;
        }

        try {
            $checkout = $this->checkout->create($providerPayload);
        } catch (\Throwable $exception) {
            $invoice->update([
                'status' => OdkBillingInvoice::STATUS_FAILED,
                'metadata' => [...($invoice->metadata ?? []), 'checkout_error' => $exception::class],
            ]);

            throw $exception;
        }

        $invoice->update([
            'provider_reference' => $checkout['reference'] ?? $reference,
            'metadata' => [...($invoice->metadata ?? []), 'checkout' => $checkout],
        ]);
        $this->audit->record('billing.checkout_started', $invoice, ['plan_code' => $plan->code], $workspace, $actor);

        return [
            'invoice' => $invoice->fresh()->load('plan'),
            'checkout' => $checkout,
        ];
    }

    public function verifyPayment(OdkWorkspace $workspace, string $reference, OdkUser $actor): array
    {
        $invoice = $workspace->billingInvoices()
            ->where(function ($query) use ($reference): void {
                $query->where('reference', $reference)->orWhere('provider_reference', $reference);
            })
            ->with(['plan', 'subscription'])
            ->firstOrFail();

        if ($invoice->status === OdkBillingInvoice::STATUS_PAID) {
            return ['invoice' => $invoice, 'subscription' => $invoice->subscription?->load('plan'), 'already_paid' => true];
        }

        $result = $this->verification->verify($invoice->provider_reference ?: $invoice->reference, $invoice->provider);
        if (($result['status'] ?? null) !== 'success') {
            $invoice->update([
                'status' => OdkBillingInvoice::STATUS_FAILED,
                'metadata' => [...($invoice->metadata ?? []), 'verification' => $result],
            ]);

            throw ValidationException::withMessages(['reference' => ['The payment has not been completed.']]);
        }

        $amountMatches = (int) ($result['amount'] ?? 0) === (int) round((float) $invoice->amount * 100);
        $currencyMatches = strtoupper((string) ($result['currency'] ?? '')) === strtoupper((string) $invoice->currency);

        if (!$amountMatches || !$currencyMatches) {
            $invoice->update([
                'status' => OdkBillingInvoice::STATUS_FAILED,
                'metadata' => [...($invoice->metadata ?? []), 'verification' => $result, 'verification_error' => 'amount_or_currency_mismatch'],
            ]);

            throw ValidationException::withMessages(['reference' => ['The payment amount or currency does not match this invoice.']]);
        }

        return $this->applySuccessfulPayment($invoice, $result, 'billing.payment_verified', $actor, $reference);
    }

    public function cancel(OdkWorkspace $workspace, OdkUser $actor): OdkWorkspaceSubscription
    {
        $subscription = $this->subscriptions->ensureFor($workspace);
        $subscription->update(['cancel_at_period_end' => true]);
        $this->audit->record('billing.subscription_cancel_scheduled', $subscription, [], $workspace, $actor);

        return $subscription->fresh()->load('plan');
    }

    public function resume(OdkWorkspace $workspace, OdkUser $actor): OdkWorkspaceSubscription
    {
        $subscription = $this->subscriptions->ensureFor($workspace);
        $subscription->update(['cancel_at_period_end' => false]);
        $this->audit->record('billing.subscription_resumed', $subscription, [], $workspace, $actor);

        return $subscription->fresh()->load('plan');
    }

    public function expireDueSubscriptions(): int
    {
        $count = 0;
        $freePlan = OdkPlan::query()->where('code', 'free')->first();

        if (!$freePlan) {
            return 0;
        }

        OdkWorkspaceSubscription::query()
            ->whereNotNull('provider')
            ->whereIn('status', [OdkWorkspaceSubscription::STATUS_ACTIVE, OdkWorkspaceSubscription::STATUS_PAST_DUE])
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<', now())
            ->with('workspace')
            ->chunkById(100, function ($subscriptions) use (&$count, $freePlan): void {
                foreach ($subscriptions as $subscription) {
                    $subscription->update([
                        'plan_id' => $freePlan->id,
                        'status' => OdkWorkspaceSubscription::STATUS_ACTIVE,
                        'provider' => null,
                        'provider_subscription_id' => null,
                        'current_period_start' => now()->startOfMonth(),
                        'current_period_end' => now()->endOfMonth(),
                        'cancel_at_period_end' => false,
                        'metadata' => [...($subscription->metadata ?? []), 'expired_at' => now()->toIso8601String()],
                    ]);
                    $this->audit->record('billing.subscription_expired', $subscription, [], $subscription->workspace, null);
                    $count++;
                }
            });

        return $count;
    }

    /** @param array<string, mixed> $event */
    public function handleWebhook(array $event): array
    {
        return match ($event['type'] ?? null) {
            'payment_succeeded' => $this->handleWebhookPayment($event),
            'payment_failed' => $this->handleWebhookPaymentFailure($event),
            'subscription_canceled' => $this->handleWebhookSubscriptionStatus($event, OdkWorkspaceSubscription::STATUS_CANCELED, 'billing.subscription_canceled'),
            'subscription_cancel_scheduled' => $this->handleWebhookSubscriptionStatus($event, null, 'billing.subscription_cancel_scheduled'),
            'payment_refunded' => $this->handleWebhookRefund($event),
            default => ['handled' => false, 'reason' => 'unsupported_event'],
        };
    }

    /** @param array<string, mixed> $event */
    private function handleWebhookPayment(array $event): array
    {
        $invoice = $this->invoiceForEvent($event);
        $subscription = $this->subscriptionForEvent($event, $invoice);

        if (!$invoice && !$subscription) {
            return ['handled' => false, 'reason' => 'invoice_or_subscription_not_found'];
        }

        if (!$invoice) {
            $plan = $subscription?->plan;
            if (!$plan || !$event['reference'] || !isset($event['amount'], $event['currency'])) {
                return ['handled' => false, 'reason' => 'renewal_data_incomplete'];
            }

            $invoice = OdkBillingInvoice::create([
                'uuid' => (string) Str::uuid(),
                'workspace_id' => $subscription->workspace_id,
                'subscription_id' => $subscription->id,
                'plan_id' => $plan->id,
                'provider' => $event['provider'],
                'provider_reference' => $event['reference'],
                'reference' => $event['reference'],
                'amount' => ((int) $event['amount']) / 100,
                'currency' => $event['currency'],
                'status' => OdkBillingInvoice::STATUS_PENDING,
                'metadata' => ['source' => 'webhook', 'event' => $event['type']],
            ]);
        }

        if ($invoice->status === OdkBillingInvoice::STATUS_PAID) {
            return ['handled' => true, 'already_paid' => true, 'invoice_id' => $invoice->id];
        }

        return $this->applySuccessfulPayment($invoice, [
            'provider' => $event['provider'],
            'reference' => $event['reference'] ?: $invoice->provider_reference,
            'subscription_reference' => $event['subscription_reference'],
            'status' => 'success',
            'amount' => $event['amount'],
            'currency' => $event['currency'],
            'raw' => $event['raw'] ?? [],
        ], 'billing.webhook_payment_succeeded');
    }

    /** @param array<string, mixed> $event */
    private function handleWebhookPaymentFailure(array $event): array
    {
        $invoice = $this->invoiceForEvent($event);
        $subscription = $this->subscriptionForEvent($event, $invoice);

        if ($invoice && in_array($invoice->status, [OdkBillingInvoice::STATUS_PAID, OdkBillingInvoice::STATUS_REFUNDED], true)) {
            return ['handled' => true, 'already_final' => true, 'invoice_id' => $invoice->id];
        }

        if ($invoice) {
            $invoice->update([
                'status' => OdkBillingInvoice::STATUS_FAILED,
                'metadata' => [...($invoice->metadata ?? []), 'webhook' => $event['raw'] ?? []],
            ]);
        }

        if ($subscription) {
            $subscription->update(['status' => OdkWorkspaceSubscription::STATUS_PAST_DUE]);
            $this->audit->record('billing.payment_failed', $subscription, [], $subscription->workspace, null);
        }

        return ['handled' => (bool) ($invoice || $subscription), 'status' => OdkWorkspaceSubscription::STATUS_PAST_DUE];
    }

    /** @param array<string, mixed> $event */
    private function handleWebhookSubscriptionStatus(array $event, ?string $status, string $auditAction): array
    {
        $subscription = $this->subscriptionForEvent($event);
        if (!$subscription) {
            return ['handled' => false, 'reason' => 'subscription_not_found'];
        }

        if ($status) {
            $subscription->update(['status' => $status, 'cancel_at_period_end' => false]);
        } else {
            $subscription->update(['cancel_at_period_end' => true]);
        }
        $this->audit->record($auditAction, $subscription, ['provider_event' => $event['type']], $subscription->workspace, null);

        return ['handled' => true, 'status' => $subscription->fresh()->status];
    }

    /** @param array<string, mixed> $event */
    private function handleWebhookRefund(array $event): array
    {
        $invoice = $this->invoiceForEvent($event);
        if (!$invoice) {
            return ['handled' => false, 'reason' => 'invoice_not_found'];
        }

        $invoice->update([
            'status' => OdkBillingInvoice::STATUS_REFUNDED,
            'metadata' => [...($invoice->metadata ?? []), 'webhook' => $event['raw'] ?? []],
        ]);
        if ($invoice->subscription) {
            $invoice->subscription->update(['status' => OdkWorkspaceSubscription::STATUS_CANCELED]);
        }
        $this->audit->record('billing.payment_refunded', $invoice, [], $invoice->workspace, null);

        return ['handled' => true, 'status' => OdkBillingInvoice::STATUS_REFUNDED];
    }

    /** @param array<string, mixed> $event */
    private function invoiceForEvent(array $event): ?OdkBillingInvoice
    {
        $reference = $event['reference'] ?? null;
        if (!$reference) {
            return null;
        }

        return OdkBillingInvoice::query()
            ->where('provider', $event['provider'])
            ->where(fn ($query) => $query->where('reference', $reference)->orWhere('provider_reference', $reference))
            ->with(['subscription.plan', 'workspace'])
            ->first();
    }

    /** @param array<string, mixed> $event */
    private function subscriptionForEvent(array $event, ?OdkBillingInvoice $invoice = null): ?OdkWorkspaceSubscription
    {
        if ($invoice?->subscription) {
            return $invoice->subscription;
        }

        $reference = $event['subscription_reference'] ?? null;
        if (!$reference) {
            return null;
        }

        return OdkWorkspaceSubscription::query()
            ->where('provider', $event['provider'])
            ->where('provider_subscription_id', $reference)
            ->with(['plan', 'workspace'])
            ->first();
    }

    /** @param array<string, mixed> $result */
    private function applySuccessfulPayment(OdkBillingInvoice $invoice, array $result, string $auditAction, ?OdkUser $actor = null, ?string $reference = null): array
    {
        $amountMatches = (int) ($result['amount'] ?? 0) === (int) round((float) $invoice->amount * 100);
        $currencyMatches = strtoupper((string) ($result['currency'] ?? '')) === strtoupper((string) $invoice->currency);

        if (!$amountMatches || !$currencyMatches) {
            $invoice->update([
                'status' => OdkBillingInvoice::STATUS_FAILED,
                'metadata' => [...($invoice->metadata ?? []), 'verification' => $result, 'verification_error' => 'amount_or_currency_mismatch'],
            ]);

            if ($actor) {
                throw ValidationException::withMessages(['reference' => ['The payment amount or currency does not match this invoice.']]);
            }

            return ['handled' => false, 'reason' => 'amount_or_currency_mismatch'];
        }

        $invoice->loadMissing(['subscription', 'plan', 'workspace']);
        $subscription = $invoice->subscription ?: $this->subscriptions->ensureFor($invoice->workspace);
        $subscription->update([
            'plan_id' => $invoice->plan_id,
            'status' => OdkWorkspaceSubscription::STATUS_ACTIVE,
            'provider' => $invoice->provider,
            'provider_customer_id' => $result['customer_code'] ?? $subscription->provider_customer_id,
            'provider_subscription_id' => $result['subscription_reference'] ?? $result['reference'] ?? $invoice->provider_reference,
            'current_period_start' => now(),
            'current_period_end' => $this->periodEnd($invoice->plan),
            'cancel_at_period_end' => false,
            'metadata' => [...($subscription->metadata ?? []), 'last_payment' => $result],
        ]);
        $invoice->update([
            'status' => OdkBillingInvoice::STATUS_PAID,
            'paid_at' => now(),
            'subscription_id' => $subscription->id,
            'provider_reference' => $invoice->provider_reference ?: ($result['reference'] ?? null),
            'metadata' => [...($invoice->metadata ?? []), 'verification' => $result],
        ]);
        $this->audit->record($auditAction, $invoice, ['reference' => $reference ?: ($result['reference'] ?? null)], $invoice->workspace, $actor);

        return [
            'handled' => true,
            'invoice' => $invoice->fresh()->load('plan'),
            'subscription' => $subscription->fresh()->load('plan'),
            'already_paid' => false,
        ];
    }

    private function periodEnd(OdkPlan $plan)
    {
        return $plan->billing_interval === 'yearly' ? now()->addYear() : now()->addMonth();
    }
}
