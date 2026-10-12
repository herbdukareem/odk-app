<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkBillingInvoice extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELED = 'canceled';
    public const STATUS_REFUNDED = 'refunded';

    protected $guarded = ['id'];

    protected $casts = [
        'amount' => 'decimal:2',
        'metadata' => 'array',
        'paid_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(OdkWorkspace::class, 'workspace_id');
    }

    public function subscription()
    {
        return $this->belongsTo(OdkWorkspaceSubscription::class, 'subscription_id');
    }

    public function plan()
    {
        return $this->belongsTo(OdkPlan::class, 'plan_id');
    }
}
