<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkPlanVersion extends Model
{
    public const UPDATED = 'updated';
    public const CREATED = 'created';
    public const ARCHIVED = 'archived';
    public const RESTORED = 'restored';

    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(OdkPlan::class, 'plan_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(OdkUser::class, 'changed_by_user_id');
    }
}
