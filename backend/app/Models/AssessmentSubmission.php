<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOdkWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentSubmission extends Model
{
    use BelongsToOdkWorkspace, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_UNDER_REVIEW = 'under_review';
    public const STATUS_RETURNED = 'returned';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_APPROVED = 'approved';

    protected $guarded = ['id'];

    protected $casts = [
        'assessment_date' => 'date',
        'score_total' => 'float',
        'score_details' => 'array',
        'gps_latitude' => 'float',
        'gps_longitude' => 'float',
        'gps_accuracy_meters' => 'float',
        'facility_snapshot' => 'array',
        'assessor_snapshot' => 'array',
        'device_snapshot' => 'array',
        'answers_snapshot' => 'array',
        'validation_result' => 'array',
        'started_at' => 'datetime',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id');
    }

    public function formVersion()
    {
        return $this->belongsTo(AssessmentFormVersion::class, 'assessment_form_version_id');
    }

    public function exercise()
    {
        return $this->belongsTo(AssessmentExercise::class, 'assessment_exercise_id');
    }

    public function assignment()
    {
        return $this->belongsTo(AssessmentAssignment::class, 'assessment_assignment_id');
    }

    public function facility()
    {
        return $this->belongsTo(ReferenceFacility::class, 'reference_facility_id');
    }

    public function target()
    {
        return $this->belongsTo(OdkTarget::class, 'odk_target_id');
    }

    public function answers()
    {
        return $this->hasMany(AssessmentSubmissionAnswer::class)->orderBy('field_key');
    }

    public function attachments()
    {
        return $this->hasMany(AssessmentSubmissionAttachment::class)->latest();
    }

    public function reviews()
    {
        return $this->hasMany(AssessmentSubmissionReview::class)->latest();
    }

    public function syncEvents()
    {
        return $this->hasMany(AssessmentSyncEvent::class)->latest();
    }
}
