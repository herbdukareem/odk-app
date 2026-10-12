<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkPlan extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'price_amount' => 'decimal:2',
        'limits' => 'array',
        'features' => 'array',
        'active' => 'boolean',
    ];

    public function subscriptions()
    {
        return $this->hasMany(OdkWorkspaceSubscription::class, 'plan_id');
    }

    public function invoices()
    {
        return $this->hasMany(OdkBillingInvoice::class, 'plan_id');
    }

    public function versions()
    {
        return $this->hasMany(OdkPlanVersion::class, 'plan_id');
    }

    public function limit(string $metric): ?int
    {
        $limit = $this->limits[$metric] ?? null;

        return $limit === null ? null : (int) $limit;
    }
}
