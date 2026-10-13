<?php

namespace Database\Seeders;

use App\Models\OdkPlan;
use Illuminate\Database\Seeder;

class OdkSaasPlanSeeder extends Seeder
{
    public function run(): void
    {
        OdkPlan::firstOrCreate(['code' => 'free'], [
            'name' => 'Free',
            'description' => 'A starter plan for trying DataKit with a small team.',
            'price_amount' => 0,
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'limits' => ['members' => 5, 'forms' => 3, 'exercises' => 5, 'assignments' => 25, 'submissions' => 1000],
            'features' => ['offline_mobile', 'online_submission', 'basic_reports'],
            'active' => true,
            'sort_order' => 1,
        ]);

        OdkPlan::firstOrCreate(['code' => 'professional'], [
            'name' => 'Professional',
            'description' => 'For growing teams running multiple assessment programmes. Pricing is configured before checkout is enabled.',
            'price_amount' => (float) (config('billing.professional_price') ?? 0),
            'currency' => 'NGN',
            'billing_interval' => 'monthly',
            'limits' => ['members' => 25, 'forms' => 25, 'exercises' => 50, 'assignments' => 500, 'submissions' => 25000],
            'features' => ['offline_mobile', 'online_submission', 'advanced_reports', 'priority_support'],
            'active' => true,
            'sort_order' => 2,
        ]);
    }
}
