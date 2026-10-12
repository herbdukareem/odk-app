<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkWorkspaceSubscription extends Model
{
    public const STATUS_TRIALING = 'trialing';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAST_DUE = 'past_due';
    public const STATUS_CANCELED = 'canceled';

    protected $guarded = ['id'];

    protected $casts = [
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'trial_ends_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'metadata' => 'array',
    ];

    public function workspace()
    {
        return $this->belongsTo(OdkWorkspace::class, 'workspace_id');
    }

    public function plan()
    {
        return $this->belongsTo(OdkPlan::class, 'plan_id');
    }

    public function invoices()
    {
        return $this->hasMany(OdkBillingInvoice::class, 'subscription_id');
    }
}
