<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkRole extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function users()
    {
        return $this->hasMany(OdkUser::class, 'odk_role_id');
    }
}
