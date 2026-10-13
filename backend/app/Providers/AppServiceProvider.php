<?php

namespace App\Providers;

use App\Services\Billing\BillingGatewayManager;
use App\Services\Billing\PaystackBillingGateway;
use App\Support\OdkWorkspaceContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(OdkWorkspaceContext::class, fn () => new OdkWorkspaceContext());
        $this->app->singleton(BillingGatewayManager::class, function ($app): BillingGatewayManager {
            return new BillingGatewayManager([
                $app->make(PaystackBillingGateway::class),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}

