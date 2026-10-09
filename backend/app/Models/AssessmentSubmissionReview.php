<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSubmissionReview extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function submission()
    {
        return $this->belongsTo(AssessmentSubmission::class, 'assessment_submission_id');
    }
}
