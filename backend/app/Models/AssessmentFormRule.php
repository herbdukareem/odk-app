<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormRule extends Model
{
    use SoftDeletes;

    public const TYPE_VALIDATION = 'validation';
    public const TYPE_VISIBILITY = 'visibility';
    public const TYPE_SCORING = 'scoring';
    public const TYPE_EVIDENCE = 'evidence';
    public const TYPE_SIGNATURE = 'signature';
    public const TYPE_REVIEW_FLAG = 'review_flag';

    protected $guarded = ['id'];

    protected $casts = [
        'condition' => 'array',
        'action' => 'array',
        'enabled' => 'boolean',
        'requires_review' => 'boolean',
    ];

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id');
    }

    public function field()
    {
        return $this->belongsTo(AssessmentFormField::class, 'assessment_form_field_id');
    }
}
