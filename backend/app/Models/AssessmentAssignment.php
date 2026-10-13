<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOdkWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentAssignment extends Model
{
    use BelongsToOdkWorkspace, SoftDeletes;

    public const ASSIGNEE_USER = 'user';
    public const ASSIGNEE_TEAM = 'team';

    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    protected $guarded = ['id'];

    protected $casts = [
        'allow_duplicate_submission' => 'boolean',
        'assigned_at' => 'datetime',
        'due_at' => 'date',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'completed_at' => 'datetime',
        'settings' => 'array',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function exercise()
    {
        return $this->belongsTo(AssessmentExercise::class, 'assessment_exercise_id');
    }

    public function facility()
    {
        return $this->belongsTo(ReferenceFacility::class, 'reference_facility_id');
    }

    public function target()
    {
        return $this->belongsTo(OdkTarget::class, 'odk_target_id');
    }

    public function user()
    {
        return $this->belongsTo(OdkUser::class, 'odk_user_id');
    }

    public function team()
    {
        return $this->belongsTo(OdkTeam::class, 'odk_team_id');
    }

    public function submissions()
    {
        return $this->hasMany(AssessmentSubmission::class);
    }
}
