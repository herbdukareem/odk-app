<?php

namespace Tests\Feature;

use App\Models\OdkRole;
use App\Models\OdkPlan;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OdkSaasTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_gets_a_free_subscription_and_usage_snapshot(): void
    {
        [$user, $workspace] = $this->createWorkspaceUser('owner@example.com', 'Usage workspace');

        $this->withToken($user->createToken('test')->plainTextToken)
            ->getJson('/api/subscription/current')
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonPath('data.usage.0.metric', 'members');

        $this->assertDatabaseHas('odk_workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'status' => 'active',
        ]);
    }

    public function test_free_plan_blocks_forms_after_limit(): void
    {
        [$user] = $this->createWorkspaceUser('forms@example.com', 'Forms workspace');
        $token = $user->createToken('test')->plainTextToken;

        foreach (['One', 'Two', 'Three'] as $title) {
            $this->withToken($token)->postJson('/api/forms', [
                'title' => $title . ' form',
                'requires_scoring' => false,
            ])->assertCreated();
        }

        $this->withToken($token)
            ->postJson('/api/forms', ['title' => 'Blocked form', 'requires_scoring' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('forms');
    }

    public function test_platform_status_changes_are_audited(): void
    {
        [$owner, $workspace] = $this->createWorkspaceUser('audit-owner@example.com', 'Audit workspace');

        Sanctum::actingAs($owner);
        $this->getJson('/api/platform/plans')
            ->assertForbidden();

        $platformRole = OdkRole::firstOrCreate(
            ['key' => 'platform-admin'],
            ['name' => 'Platform administrator', 'description' => null, 'permissions' => ['*'], 'sort_order' => 0]
        );
        $platformUser = OdkUser::create([
            'uuid' => fake()->uuid(),
            'odk_role_id' => $platformRole->id,
            'name' => 'Platform Admin',
            'email' => 'platform@example.com',
            'status' => OdkUser::STATUS_ACTIVE,
            'is_platform_admin' => true,
            'password' => Hash::make('password'),
        ]);
        $this->assertTrue($platformUser->fresh()->isPlatformAdmin());

        Sanctum::actingAs($platformUser);
        $this->patchJson('/api/platform/workspaces/' . $workspace->id . '/status', ['status' => 'suspended'])
            ->assertOk();

        $this->assertDatabaseHas('odk_audit_logs', [
            'workspace_id' => $workspace->id,
            'actor_user_id' => $platformUser->id,
            'action' => 'platform.workspace_status_updated',
        ]);

        $freePlan = OdkPlan::create([
            'code' => 'free',
            'name' => 'Free',
            'description' => 'Free plan',
            'price_amount' => 0,
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'limits' => ['members' => 5],
            'features' => [],
            'active' => true,
            'sort_order' => 1,
        ]);

        $plan = OdkPlan::create([
            'code' => 'platform-plan',
            'name' => 'Platform plan',
            'description' => 'Initial description',
            'price_amount' => 0,
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'limits' => ['members' => 5],
            'features' => ['basic_reports'],
            'active' => true,
            'sort_order' => 3,
        ]);

        Sanctum::actingAs($platformUser);
        $this->patchJson('/api/platform/plans/' . $plan->id, [
                'name' => 'Platform Plus',
                'description' => 'Updated description',
                'price_amount' => 75000,
                'currency' => 'ngn',
                'billing_interval' => 'yearly',
                'limits' => ['members' => 50, 'forms' => 100, 'exercises' => null, 'assignments' => 500, 'submissions' => 50000],
                'features' => ['advanced_reports', 'priority_support'],
                'active' => true,
                'sort_order' => 2,
            ])
            ->assertOk()
            ->assertJsonPath('data.currency', 'NGN')
            ->assertJsonPath('data.price_amount', '75000.00');

        $this->assertDatabaseHas('odk_plans', [
            'id' => $plan->id,
            'name' => 'Platform Plus',
            'price_amount' => 75000,
            'currency' => 'NGN',
        ]);
        $this->assertDatabaseHas('odk_audit_logs', [
            'actor_user_id' => $platformUser->id,
            'action' => 'platform.plan_updated',
        ]);

        Sanctum::actingAs($platformUser);
        $created = $this->postJson('/api/platform/plans', [
                'code' => 'starter-plus',
                'name' => 'Starter Plus',
                'description' => 'A second plan',
                'price_amount' => 25000,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'provider_plan_code' => 'PLN_starter_plus',
                'limits' => ['members' => 10, 'forms' => 10, 'exercises' => 10, 'assignments' => 50, 'submissions' => 5000],
                'features' => ['online_submission'],
                'active' => true,
                'sort_order' => 4,
            ])
            ->assertCreated()
            ->json('data');

        Sanctum::actingAs($platformUser);
        $this->postJson('/api/platform/plans/' . $created['id'] . '/archive')
            ->assertOk()
            ->assertJsonPath('data.active', false);

        Sanctum::actingAs($platformUser);
        $this->getJson('/api/platform/plans/' . $created['id'] . '/history')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        Sanctum::actingAs($platformUser);
        $this->postJson('/api/platform/plans/' . $freePlan->id . '/archive')
            ->assertUnprocessable();
    }

    public function test_paid_upgrade_creates_invoice_and_activates_after_verification(): void
    {
        [$user, $workspace] = $this->createWorkspaceUser('billing@example.com', 'Billing workspace');
        $plan = OdkPlan::create([
            'code' => 'professional',
            'name' => 'Professional',
            'description' => 'Growing teams',
            'price_amount' => 50000,
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'limits' => ['members' => 25, 'forms' => 25, 'exercises' => 50, 'assignments' => 500, 'submissions' => 25000],
            'features' => ['advanced_reports'],
            'active' => true,
            'sort_order' => 2,
        ]);
        $token = $user->createToken('test')->plainTextToken;

        config(['billing.gateways.paystack.secret_key' => 'test-secret']);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['reference' => 'PSK-123', 'authorization_url' => 'https://paystack.test/checkout'],
            ]),
        ]);

        $checkout = $this->withToken($token)
            ->postJson('/api/subscription/checkout', ['plan_code' => $plan->code])
            ->assertOk()
            ->assertJsonPath('data.checkout.reference', 'PSK-123')
            ->json('data');

        $this->assertDatabaseHas('odk_billing_invoices', [
            'uuid' => $checkout['invoice']['uuid'],
            'provider_reference' => 'PSK-123',
            'status' => 'pending',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/verify/*' => Http::response([
                'status' => true,
                'data' => ['status' => 'success', 'reference' => 'PSK-123', 'amount' => 5000000, 'currency' => 'NGN'],
            ]),
        ]);

        $this->withToken($token)
            ->postJson('/api/subscription/verify', ['reference' => 'PSK-123'])
            ->assertOk()
            ->assertJsonPath('data.subscription.plan.code', 'professional');

        $this->assertDatabaseHas('odk_billing_invoices', [
            'uuid' => $checkout['invoice']['uuid'],
            'status' => 'paid',
        ]);
        $this->assertDatabaseHas('odk_workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->withToken($token)->postJson('/api/subscription/cancel')->assertOk()->assertJsonPath('data.cancel_at_period_end', true);
        $this->withToken($token)->postJson('/api/subscription/resume')->assertOk()->assertJsonPath('data.cancel_at_period_end', false);
    }

    public function test_signed_paystack_webhooks_update_subscription_statuses(): void
    {
        [$user, $workspace] = $this->createWorkspaceUser('webhooks@example.com', 'Webhook workspace');
        $plan = OdkPlan::create([
            'code' => 'recurring-professional',
            'name' => 'Recurring Professional',
            'description' => 'Recurring plan',
            'price_amount' => 50000,
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'provider_plan_code' => 'PLN_professional',
            'limits' => ['members' => 25],
            'features' => ['advanced_reports'],
            'active' => true,
            'sort_order' => 2,
        ]);
        $token = $user->createToken('test')->plainTextToken;
        config([
            'billing.gateways.paystack.secret_key' => 'test-secret',
            'billing.gateways.paystack.webhook_secret' => 'test-secret',
        ]);

        Http::fake([
            'https://api.paystack.co/transaction/initialize' => Http::response([
                'status' => true,
                'data' => ['reference' => 'PSK-WEBHOOK', 'authorization_url' => 'https://paystack.test/checkout'],
            ]),
        ]);

        $this->withToken($token)
            ->postJson('/api/subscription/checkout', ['plan_code' => $plan->code])
            ->assertOk();

        $payload = json_encode([
            'event' => 'charge.success',
            'data' => [
                'reference' => 'PSK-WEBHOOK',
                'amount' => 5000000,
                'currency' => 'NGN',
                'status' => 'success',
                'subscription_code' => 'SUB_WEBHOOK',
            ],
        ], JSON_THROW_ON_ERROR);

        $this->call('POST', '/api/billing/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $payload, 'test-secret')], $payload)
            ->assertOk()
            ->assertJsonPath('data.handled', true);

        $this->assertDatabaseHas('odk_workspace_subscriptions', [
            'workspace_id' => $workspace->id,
            'provider_subscription_id' => 'SUB_WEBHOOK',
            'status' => 'active',
        ]);

        $failurePayload = json_encode([
            'event' => 'invoice.payment_failed',
            'data' => ['subscription_code' => 'SUB_WEBHOOK', 'status' => 'failed'],
        ], JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/billing/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $failurePayload, 'test-secret')], $failurePayload)
            ->assertOk()
            ->assertJsonPath('data.status', 'past_due');

        $cancelPayload = json_encode([
            'event' => 'subscription.disable',
            'data' => ['subscription_code' => 'SUB_WEBHOOK'],
        ], JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/billing/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $cancelPayload, 'test-secret')], $cancelPayload)
            ->assertOk()
            ->assertJsonPath('data.status', 'canceled');

        $refundPayload = json_encode([
            'event' => 'refund.processed',
            'data' => ['transaction_reference' => 'PSK-WEBHOOK'],
        ], JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/billing/paystack/webhook', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $refundPayload, 'test-secret')], $refundPayload)
            ->assertOk()
            ->assertJsonPath('data.status', 'refunded');

        $this->assertDatabaseHas('odk_billing_invoices', [
            'provider_reference' => 'PSK-WEBHOOK',
            'status' => 'refunded',
        ]);
    }

    private function createWorkspaceUser(string $email, string $workspaceName): array
    {
        $role = OdkRole::firstOrCreate(
            ['key' => 'member'],
            ['name' => 'Member', 'description' => null, 'permissions' => [], 'sort_order' => 1]
        );

        $user = OdkUser::create([
            'uuid' => fake()->uuid(),
            'odk_role_id' => $role->id,
            'name' => 'Workspace User',
            'email' => $email,
            'status' => OdkUser::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);

        $workspace = OdkWorkspace::create([
            'uuid' => fake()->uuid(),
            'slug' => str($workspaceName)->slug(),
            'name' => $workspaceName,
            'type' => OdkWorkspace::TYPE_ORGANIZATION,
            'status' => OdkWorkspace::STATUS_ACTIVE,
            'owner_user_id' => $user->id,
        ]);

        $workspace->users()->attach($user->id, [
            'role_key' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }
}
