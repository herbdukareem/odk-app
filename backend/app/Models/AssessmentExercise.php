<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOdkWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentExercise extends Model
{
    use BelongsToOdkWorkspace, SoftDeletes;

    public const PERIOD_QUARTER = 'quarter';
    public const PERIOD_MONTH = 'month';
    public const PERIOD_CUSTOM = 'custom';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_CLOSED = 'closed';
    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = ['id'];

    protected $casts = [
        'year' => 'integer',
        'quarter' => 'integer',
        'month' => 'integer',
        'starts_at' => 'date',
        'ends_at' => 'date',
        'settings' => 'array',
        'activated_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id');
    }

    public function formVersion()
    {
        return $this->belongsTo(AssessmentFormVersion::class, 'assessment_form_version_id');
    }

    public function assignments()
    {
        return $this->hasMany(AssessmentAssignment::class);
    }

    public function submissions()
    {
        return $this->hasMany(AssessmentSubmission::class);
    }
}
