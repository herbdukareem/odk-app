<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentSubmissionAttachment extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $appends = [
        'download_url',
    ];

    protected $casts = [
        'metadata' => 'array',
        'captured_at' => 'datetime',
    ];

    public function submission()
    {
        return $this->belongsTo(AssessmentSubmission::class, 'assessment_submission_id');
    }

    public function getDownloadUrlAttribute(): ?string
    {
        if (!$this->path || !$this->assessment_submission_id) {
            return null;
        }

        $submission = $this->relationLoaded('submission')
            ? $this->submission
            : AssessmentSubmission::query()->select('uuid')->find($this->assessment_submission_id);

        if (!$submission) {
            return null;
        }

        return url("/api/submissions/{$submission->uuid}/attachments/{$this->id}/download");
    }
}
