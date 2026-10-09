<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceWard extends Model
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
}

