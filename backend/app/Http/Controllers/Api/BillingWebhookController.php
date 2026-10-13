<?php

namespace App\Http\Controllers\Api;

use App\Services\Billing\BillingSubscriptionService;
use App\Services\Billing\BillingWebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BillingWebhookController
{
    public function paystack(Request $request, BillingWebhookService $webhooks, BillingSubscriptionService $billing): JsonResponse
    {
        $event = $webhooks->normalize(
            'paystack',
            $request->getContent(),
            $request->header('x-paystack-signature'),
        );

        if (!$event) {
            return response()->json(['success' => true, 'message' => 'Event ignored.']);
        }

        return response()->json(['success' => true, 'data' => $billing->handleWebhook($event)]);
    }
}
