<?php

namespace App\Http\Controllers\Api;

use App\Models\OdkUser;
use App\Models\OdkPlan;
use App\Models\OdkPlanVersion;
use App\Models\OdkWorkspace;
use App\Models\OdkAuditLog;
use App\Services\Saas\OdkAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OdkPlatformController
{
    public function plans(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        return response()->json([
            'success' => true,
            'data' => OdkPlan::query()
                ->withCount(['subscriptions', 'invoices'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function updatePlan(Request $request, OdkPlan $plan, OdkAuditLogger $audit): JsonResponse
    {
        $this->assertPlatformAdmin($request);
        $wasActive = $plan->active;
        $data = $this->validatePlan($request, $plan);

        abort_if($plan->code === 'free' && !$data['active'], 422, 'The free plan must remain available.');

        $data['currency'] = strtoupper($data['currency']);
        $plan->update($data);
        $versionAction = $wasActive && !$plan->active
            ? OdkPlanVersion::ARCHIVED
            : (!$wasActive && $plan->active ? OdkPlanVersion::RESTORED : OdkPlanVersion::UPDATED);
        $this->recordPlanVersion($plan, $versionAction, $request);
        $audit->record('platform.plan_updated', $plan, [
            'plan_code' => $plan->code,
            'price_amount' => $plan->price_amount,
            'currency' => $plan->currency,
            'active' => $plan->active,
        ], null, $request->user());

        return response()->json(['success' => true, 'data' => $plan->fresh()]);
    }

    public function storePlan(Request $request, OdkAuditLogger $audit): JsonResponse
    {
        $this->assertPlatformAdmin($request);
        $data = $this->validatePlan($request);
        $data['currency'] = strtoupper($data['currency']);
        $plan = OdkPlan::create($data);
        $this->recordPlanVersion($plan, OdkPlanVersion::CREATED, $request);
        $audit->record('platform.plan_created', $plan, ['plan_code' => $plan->code], null, $request->user());

        return response()->json(['success' => true, 'data' => $plan->fresh()], 201);
    }

    public function archivePlan(Request $request, OdkPlan $plan, OdkAuditLogger $audit): JsonResponse
    {
        $this->assertPlatformAdmin($request);
        abort_if($plan->code === 'free', 422, 'The free plan must remain available.');
        $plan->update(['active' => false]);
        $this->recordPlanVersion($plan, OdkPlanVersion::ARCHIVED, $request);
        $audit->record('platform.plan_archived', $plan, ['plan_code' => $plan->code], null, $request->user());

        return response()->json(['success' => true, 'data' => $plan->fresh()]);
    }

    public function restorePlan(Request $request, OdkPlan $plan, OdkAuditLogger $audit): JsonResponse
    {
        $this->assertPlatformAdmin($request);
        $plan->update(['active' => true]);
        $this->recordPlanVersion($plan, OdkPlanVersion::RESTORED, $request);
        $audit->record('platform.plan_restored', $plan, ['plan_code' => $plan->code], null, $request->user());

        return response()->json(['success' => true, 'data' => $plan->fresh()]);
    }

    public function planHistory(Request $request, OdkPlan $plan): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $plan->versions()->with('changedBy:id,name,email')->latest('created_at')->limit(100)->get(),
        ]);
    }

    public function workspaces(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        return response()->json([
            'success' => true,
            'data' => OdkWorkspace::query()
                ->with('owner:id,name,email')
                ->withCount(['users', 'forms', 'exercises', 'assignments', 'submissions'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function users(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        return response()->json([
            'success' => true,
            'data' => OdkUser::query()
                ->with(['role', 'workspaces'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $this->assertPlatformAdmin($request);

        return response()->json([
            'success' => true,
            'data' => OdkAuditLog::query()
                ->with(['actor:id,name,email', 'workspace:id,name,slug'])
                ->when($request->filled('workspace_id'), fn ($query) => $query->where('workspace_id', $request->integer('workspace_id')))
                ->latest('created_at')
                ->limit(100)
                ->get(),
        ]);
    }

    public function updateStatus(Request $request, OdkWorkspace $workspace, OdkAuditLogger $audit): JsonResponse
    {
        $this->assertPlatformAdmin($request);
        $data = $request->validate([
            'status' => ['required', 'in:active,suspended,archived'],
        ]);

        $workspace->update(['status' => $data['status']]);
        $audit->record('platform.workspace_status_updated', $workspace, ['status' => $data['status']], $workspace);

        return response()->json(['success' => true, 'data' => $workspace->fresh()->load('owner:id,name,email')]);
    }

    public function updateUserStatus(Request $request, OdkUser $user, OdkAuditLogger $audit): JsonResponse
    {
        $this->assertPlatformAdmin($request);
        $data = $request->validate([
            'status' => ['required', 'in:active,inactive,suspended'],
        ]);

        abort_if((int) $user->id === (int) $request->user()->id && $data['status'] !== OdkUser::STATUS_ACTIVE, 422, 'You cannot suspend your own platform account.');
        $user->update(['status' => $data['status']]);
        $audit->record('platform.user_status_updated', $user, ['status' => $data['status']], null, $request->user());

        return response()->json(['success' => true, 'data' => $user->fresh()->load(['role', 'workspaces'])]);
    }

    public function impersonateOwner(Request $request, OdkWorkspace $workspace, OdkAuditLogger $audit): JsonResponse
    {
        $this->assertPlatformAdmin($request);
        $owner = $workspace->owner;

        abort_unless($owner && $owner->status === OdkUser::STATUS_ACTIVE, 422, 'This workspace has no active owner.');
        $audit->record('platform.workspace_owner_impersonated', $owner, ['workspace_id' => $workspace->id], $workspace, $request->user());

        $abilities = [
            'impersonation',
            'platform-support',
            'impersonated-by:' . $request->user()->id,
            'workspace:' . $workspace->id,
        ];
        $token = $owner->createToken('odk-platform-impersonation', $abilities, now()->addHours(4))->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $owner->load(['role', 'workspaces']),
                'original_user' => $request->user()->load('role'),
                'token' => $token,
                'expires_at' => now()->addHours(4)->toIso8601String(),
            ],
        ]);
    }

    private function assertPlatformAdmin(Request $request): void
    {
        $user = $request->user('sanctum') ?? $request->user();
        abort_unless($user instanceof OdkUser && $user->isPlatformAdmin(), 403, 'Only platform administrators can access this area.');
    }

    private function validatePlan(Request $request, ?OdkPlan $plan = null): array
    {
        $providerPlanCodeRule = Rule::unique('odk_plans', 'provider_plan_code');
        if ($plan) {
            $providerPlanCodeRule->ignore($plan->id);
        }

        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_amount' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'currency' => ['required', 'string', 'size:3', 'regex:/^[A-Za-z]{3}$/'],
            'billing_interval' => ['required', 'in:monthly,yearly'],
            'provider_plan_code' => ['nullable', 'string', 'max:80', $providerPlanCodeRule],
            'limits' => ['required', 'array'],
            'limits.members' => ['nullable', 'integer', 'min:0'],
            'limits.forms' => ['nullable', 'integer', 'min:0'],
            'limits.exercises' => ['nullable', 'integer', 'min:0'],
            'limits.assignments' => ['nullable', 'integer', 'min:0'],
            'limits.submissions' => ['nullable', 'integer', 'min:0'],
            'features' => ['nullable', 'array'],
            'features.*' => ['string', 'max:80'],
            'active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0'],
        ];

        if (!$plan) {
            $rules['code'] = ['required', 'string', 'alpha_dash', 'max:40', 'unique:odk_plans,code'];
        }

        return $request->validate($rules);
    }

    private function recordPlanVersion(OdkPlan $plan, string $action, Request $request): void
    {
        OdkPlanVersion::create([
            'plan_id' => $plan->id,
            'changed_by_user_id' => $request->user()->id,
            'action' => $action,
            'snapshot' => $plan->fresh()->toArray(),
        ]);
    }
}
