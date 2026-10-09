<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormField extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'required' => 'boolean',
        'readonly' => 'boolean',
        'validation_rules' => 'array',
        'display_logic' => 'array',
        'required_logic' => 'array',
        'settings' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id');
    }

    public function section()
    {
        return $this->belongsTo(AssessmentFormSection::class, 'assessment_form_section_id');
    }

    public function options()
    {
        return $this->hasMany(AssessmentFormFieldOption::class)->orderBy('sort_order');
    }

    public function rules()
    {
        return $this->hasMany(AssessmentFormRule::class);
    }
}
