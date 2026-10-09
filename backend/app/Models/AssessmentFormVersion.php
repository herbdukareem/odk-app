<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormVersion extends Model
{
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_RETIRED = 'retired';
    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = ['id'];

    protected $casts = [
        'offline_enabled' => 'boolean',
        'requires_scoring' => 'boolean',
        'scoring_max_score' => 'decimal:2',
        'metadata_schema' => 'array',
        'sections' => 'array',
        'fields' => 'array',
        'validation_rules' => 'array',
        'visibility_rules' => 'array',
        'scoring_rules' => 'array',
        'required_evidence_rules' => 'array',
        'signature_rules' => 'array',
        'review_flags' => 'array',
        'published_at' => 'datetime',
    ];

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id');
    }
}
