<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OdkWorkspace extends Model
{
    use SoftDeletes;

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_ORGANIZATION = 'organization';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = ['id'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function owner()
    {
        return $this->belongsTo(OdkUser::class, 'owner_user_id');
    }

    public function users()
    {
        return $this->belongsToMany(OdkUser::class, 'odk_workspace_members', 'odk_workspace_id', 'odk_user_id')
            ->withPivot(['role_key', 'status', 'joined_at'])
            ->withTimestamps();
    }

    public function members()
    {
        return $this->hasMany(OdkWorkspaceMember::class, 'odk_workspace_id');
    }

    public function invitations()
    {
        return $this->hasMany(OdkWorkspaceInvitation::class, 'workspace_id');
    }

    public function forms()
    {
        return $this->hasMany(AssessmentForm::class, 'workspace_id');
    }

    public function exercises()
    {
        return $this->hasMany(AssessmentExercise::class, 'workspace_id');
    }

    public function assignments()
    {
        return $this->hasMany(AssessmentAssignment::class, 'workspace_id');
    }

    public function submissions()
    {
        return $this->hasMany(AssessmentSubmission::class, 'workspace_id');
    }

    public function teams()
    {
        return $this->hasMany(OdkTeam::class, 'workspace_id');
    }

    public function targets()
    {
        return $this->hasMany(OdkTarget::class, 'workspace_id');
    }

    public function subscription()
    {
        return $this->hasOne(OdkWorkspaceSubscription::class, 'workspace_id');
    }

    public function auditLogs()
    {
        return $this->hasMany(OdkAuditLog::class, 'workspace_id');
    }

    public function billingInvoices()
    {
        return $this->hasMany(OdkBillingInvoice::class, 'workspace_id');
    }
}
