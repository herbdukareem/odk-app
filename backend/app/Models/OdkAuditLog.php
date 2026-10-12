<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkAuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(OdkWorkspace::class, 'workspace_id');
    }

    public function actor()
    {
        return $this->belongsTo(OdkUser::class, 'actor_user_id');
    }
}
