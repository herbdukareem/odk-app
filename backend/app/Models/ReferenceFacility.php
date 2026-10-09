<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceFacility extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
        'source_payload' => 'array',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    public function lga()
    {
        return $this->belongsTo(ReferenceLga::class, 'reference_lga_id');
    }

    public function ward()
    {
        return $this->belongsTo(ReferenceWard::class, 'reference_ward_id');
    }

    public function assignments()
    {
        return $this->hasMany(AssessmentAssignment::class);
    }
}
