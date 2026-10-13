<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOdkWorkspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OdkTarget extends Model
{
    use BelongsToOdkWorkspace, SoftDeletes;

    public const TYPE_FACILITY = 'facility';
    public const TYPE_SITE = 'site';
    public const TYPE_SCHOOL = 'school';
    public const TYPE_PROJECT = 'project';
    public const TYPE_PARTICIPANT = 'participant';
    public const TYPE_NONE = 'no_target';

    protected $guarded = ['id'];

    protected $casts = [
        'metadata' => 'array',
        'active' => 'boolean',
    ];

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public static function types(): array
    {
        return [
            self::TYPE_FACILITY,
            self::TYPE_SITE,
            self::TYPE_SCHOOL,
            self::TYPE_PROJECT,
            self::TYPE_PARTICIPANT,
            self::TYPE_NONE,
        ];
    }

    public function assignments()
    {
        return $this->hasMany(AssessmentAssignment::class, 'odk_target_id');
    }

    public function submissions()
    {
        return $this->hasMany(AssessmentSubmission::class, 'odk_target_id');
    }
}
