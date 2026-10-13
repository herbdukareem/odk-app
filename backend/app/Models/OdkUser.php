<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Sanctum\HasApiTokens;

class OdkUser extends Authenticatable
{
    use HasApiTokens, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_SUSPENDED = 'suspended';

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'device_info' => 'array',
        'last_login_at' => 'datetime',
        'is_platform_admin' => 'boolean',
    ];

    public function role()
    {
        return $this->belongsTo(OdkRole::class, 'odk_role_id');
    }

    public function teams()
    {
        return $this->belongsToMany(OdkTeam::class, 'odk_team_members', 'odk_user_id', 'odk_team_id')
            ->withPivot(['member_role', 'joined_at'])
            ->withTimestamps();
    }

    public function workspaces()
    {
        return $this->belongsToMany(OdkWorkspace::class, 'odk_workspace_members', 'odk_user_id', 'odk_workspace_id')
            ->withPivot(['role_key', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function workspaceMemberships()
    {
        return $this->hasMany(OdkWorkspaceMember::class, 'odk_user_id');
    }

    public function workspaceRole(int $workspaceId): ?string
    {
        return $this->workspaceMemberships()
            ->where('odk_workspace_id', $workspaceId)
            ->where('status', 'active')
            ->value('role_key');
    }

    public function leadingTeams()
    {
        return $this->hasMany(OdkTeam::class, 'lead_user_id');
    }

    public function assessmentAssignments()
    {
        return $this->hasMany(AssessmentAssignment::class, 'odk_user_id');
    }

    public function isAdmin(): bool
    {
        return $this->relationLoaded('role')
            ? $this->role?->key === 'admin'
            : $this->role()->where('key', 'admin')->exists();
    }

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }
}
