<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OdkWorkspaceMember extends Model
{
    protected $table = 'odk_workspace_members';

    protected $guarded = ['id'];

    protected $casts = [
        'joined_at' => 'datetime',
    ];

    public function workspace()
    {
        return $this->belongsTo(OdkWorkspace::class, 'odk_workspace_id');
    }

    public function user()
    {
        return $this->belongsTo(OdkUser::class, 'odk_user_id');
    }
}
