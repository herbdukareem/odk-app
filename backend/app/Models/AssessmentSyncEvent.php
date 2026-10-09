<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentSyncEvent extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'payload' => 'array',
        'attempted_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(AssessmentSubmission::class, 'assessment_submission_id');
    }
}
