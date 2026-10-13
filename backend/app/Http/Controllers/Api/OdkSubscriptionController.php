<?php

namespace App\Http\Controllers\Api;

use App\Models\OdkPlan;
use App\Services\Billing\BillingSubscriptionService;
use App\Services\Saas\OdkSubscriptionService;
use App\Support\OdkWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OdkSubscriptionController
{
    public function plans(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => OdkPlan::query()->where('active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function current(OdkSubscriptionService $subscriptions): JsonResponse
    {
        $workspace = app(OdkWorkspaceContext::class)->current();
        abort_unless($workspace, 409, 'No active workspace is selected.');

        return response()->json(['success' => true, 'data' => $subscriptions->snapshot($workspace)]);
    }

    public function invoices(): JsonResponse
    {
        $workspace = $this->workspace();

        return response()->json([
            'success' => true,
            'data' => $workspace->billingInvoices()->with('plan:id,code,name')->latest()->limit(50)->get(),
        ]);
    }

    public function checkout(Request $request, BillingSubscriptionService $billing): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate(['plan_code' => ['required', 'string', 'exists:odk_plans,code']]);
        $workspace = $this->workspace();
        $plan = OdkPlan::query()->where('code', $data['plan_code'])->where('active', true)->firstOrFail();

        return response()->json([
            'success' => true,
            'data' => $billing->startCheckout($workspace, $plan, $request->user()),
        ]);
    }

    public function verify(Request $request, BillingSubscriptionService $billing): JsonResponse
    {
        $this->assertManager($request);
        $data = $request->validate(['reference' => ['required', 'string', 'max:255']]);

        return response()->json([
            'success' => true,
            'data' => $billing->verifyPayment($this->workspace(), $data['reference'], $request->user()),
        ]);
    }

    public function cancel(Request $request, BillingSubscriptionService $billing): JsonResponse
    {
        $this->assertManager($request);

        return response()->json(['success' => true, 'data' => $billing->cancel($this->workspace(), $request->user())]);
    }

    public function resume(Request $request, BillingSubscriptionService $billing): JsonResponse
    {
        $this->assertManager($request);

        return response()->json(['success' => true, 'data' => $billing->resume($this->workspace(), $request->user())]);
    }

    private function workspace()
    {
        $workspace = app(OdkWorkspaceContext::class)->current();
        abort_unless($workspace, 409, 'No active workspace is selected.');

        return $workspace;
    }

    private function assertManager(Request $request): void
    {
        abort_unless(app(OdkWorkspaceContext::class)->userCanManage($request->user()), 403, 'Only workspace owners and admins can manage billing.');
    }
}
