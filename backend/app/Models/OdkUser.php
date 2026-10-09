<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OdkUser extends Model
{
    use SoftDeletes;

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

    public function leadingTeams()
    {
        return $this->hasMany(OdkTeam::class, 'lead_user_id');
    }

    public function assessmentAssignments()
    {
        return $this->hasMany(AssessmentAssignment::class, 'odk_user_id');
    }
}
