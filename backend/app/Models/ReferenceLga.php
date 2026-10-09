<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferenceLga extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'active' => 'boolean',
        'source_payload' => 'array',
        'source_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];
}

