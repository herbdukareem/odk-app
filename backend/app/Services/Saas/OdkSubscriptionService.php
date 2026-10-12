<?php

namespace App\Services\Saas;

use App\Models\OdkPlan;
use App\Models\OdkWorkspace;
use App\Models\OdkWorkspaceSubscription;
use App\Support\OdkWorkspaceContext;
use Illuminate\Validation\ValidationException;

class OdkSubscriptionService
{
    public function ensureFor(OdkWorkspace $workspace): OdkWorkspaceSubscription
    {
        $freePlan = OdkPlan::query()->firstOrCreate(
            ['code' => 'free'],
            [
                'name' => 'Free',
                'description' => 'A starter plan for trying DataKit with a small team.',
                'price_amount' => 0,
                'currency' => 'NGN',
                'billing_interval' => 'monthly',
                'limits' => [
                    'members' => 5,
                    'forms' => 3,
                    'exercises' => 5,
                    'assignments' => 25,
                    'submissions' => 1000,
                ],
                'features' => ['offline_mobile', 'online_submission', 'basic_reports'],
                'active' => true,
                'sort_order' => 1,
            ]
        );

        return $workspace->subscription()->firstOrCreate(
            [],
            [
                'plan_id' => $freePlan->id,
                'status' => OdkWorkspaceSubscription::STATUS_ACTIVE,
                'current_period_start' => now()->startOfMonth(),
                'current_period_end' => now()->endOfMonth(),
            ]
        )->load('plan');
    }

    public function current(): OdkWorkspaceSubscription
    {
        $workspace = app(OdkWorkspaceContext::class)->current();
        abort_unless($workspace instanceof OdkWorkspace, 409, 'No active workspace is selected.');

        return $this->ensureFor($workspace);
    }

    public function snapshot(OdkWorkspace $workspace): array
    {
        $subscription = $this->ensureFor($workspace);
        $plan = $subscription->plan;

        $usage = [
            'members' => $workspace->members()->where('status', 'active')->count()
                + $workspace->invitations()->where('status', 'pending')->count(),
            'forms' => $workspace->forms()->whereNotIn('status', ['archived', 'retired'])->count(),
            'exercises' => $workspace->exercises()->whereNotIn('status', ['archived', 'closed'])->count(),
            'assignments' => $workspace->assignments()->whereNotIn('status', ['archived', 'completed'])->count(),
            'submissions' => $workspace->submissions()
                ->whereBetween('submitted_at', [$subscription->current_period_start ?? now()->startOfMonth(), $subscription->current_period_end ?? now()->endOfMonth()])
                ->count(),
        ];

        return [
            'subscription' => $subscription,
            'plan' => $plan,
            'usage' => collect($usage)->map(fn (int $used, string $metric): array => [
                'metric' => $metric,
                'used' => $used,
                'limit' => $plan->limit($metric),
                'unlimited' => $plan->limit($metric) === null || $plan->limit($metric) < 0,
            ])->values()->all(),
        ];
    }

    public function assertCurrentWithinLimit(string $metric): void
    {
        $workspace = app(OdkWorkspaceContext::class)->current();
        abort_unless($workspace instanceof OdkWorkspace, 409, 'No active workspace is selected.');

        $snapshot = $this->snapshot($workspace);
        $item = collect($snapshot['usage'])->firstWhere('metric', $metric);

        if (!$item || $item['unlimited'] || $item['used'] < $item['limit']) {
            return;
        }

        throw ValidationException::withMessages([
            $metric => [sprintf('Your %s plan limit for %s has been reached.', $snapshot['plan']->name, str_replace('_', ' ', $metric))],
        ]);
    }
}
