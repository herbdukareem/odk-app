<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSubmissionAnswer extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'value' => 'array',
        'score' => 'float',
        'requires_review' => 'boolean',
        'metadata' => 'array',
    ];

    public function submission()
    {
        return $this->belongsTo(AssessmentSubmission::class, 'assessment_submission_id');
    }
}
