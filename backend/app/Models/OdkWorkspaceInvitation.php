<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkWorkspaceInvitation extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REVOKED = 'revoked';
    public const STATUS_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function workspace()
    {
        return $this->belongsTo(OdkWorkspace::class, 'workspace_id');
    }

    public function invitedBy()
    {
        return $this->belongsTo(OdkUser::class, 'invited_by_user_id');
    }
}
