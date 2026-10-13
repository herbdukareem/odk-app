<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOdkWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssessmentForm extends Model
{
    use BelongsToOdkWorkspace, SoftDeletes;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_RETIRED = 'retired';
    public const STATUS_ARCHIVED = 'archived';

    protected $guarded = ['id'];

    protected $casts = [
        'requires_scoring' => 'boolean',
        'scoring_max_score' => 'decimal:2',
        'published_at' => 'datetime',
    ];

    public function versions()
    {
        return $this->hasMany(AssessmentFormVersion::class);
    }

    public function currentVersion()
    {
        return $this->belongsTo(AssessmentFormVersion::class, 'current_version_id');
    }

    public function sections()
    {
        return $this->hasMany(AssessmentFormSection::class)->orderBy('sort_order');
    }

    public function fields()
    {
        return $this->hasMany(AssessmentFormField::class)->orderBy('sort_order');
    }

    public function builderRules()
    {
        return $this->hasMany(AssessmentFormRule::class)->orderBy('sort_order');
    }

    public function publishedVersions()
    {
        return $this->versions()->where('status', AssessmentFormVersion::STATUS_PUBLISHED);
    }
}
