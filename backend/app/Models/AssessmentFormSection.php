<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentFormSection extends Model
{
    use SoftDeletes;

    protected $guarded = ['id'];

    protected $casts = [
        'is_repeatable' => 'boolean',
        'settings' => 'array',
    ];

    public function form()
    {
        return $this->belongsTo(AssessmentForm::class, 'assessment_form_id');
    }

    public function fields()
    {
        return $this->hasMany(AssessmentFormField::class)->orderBy('sort_order');
    }
}
