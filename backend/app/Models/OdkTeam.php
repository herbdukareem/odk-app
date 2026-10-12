<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOdkWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OdkTeam extends Model
{
    use BelongsToOdkWorkspace, SoftDeletes;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $guarded = ['id'];

    protected $casts = [
        'settings' => 'array',
    ];

    public function lead()
    {
        return $this->belongsTo(OdkUser::class, 'lead_user_id');
    }

    public function members()
    {
        return $this->belongsToMany(OdkUser::class, 'odk_team_members', 'odk_team_id', 'odk_user_id')
            ->withPivot(['member_role', 'joined_at'])
            ->withTimestamps();
    }

    public function assessmentAssignments()
    {
        return $this->hasMany(AssessmentAssignment::class, 'odk_team_id');
    }
}
