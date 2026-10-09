<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormFieldOption extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'score' => 'float',
        'requires_review' => 'boolean',
        'settings' => 'array',
    ];

    public function field()
    {
        return $this->belongsTo(AssessmentFormField::class, 'assessment_form_field_id');
    }
}
