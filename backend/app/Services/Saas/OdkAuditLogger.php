<?php

namespace App\Services\Saas;

use App\Models\OdkAuditLog;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Support\OdkWorkspaceContext;
use Illuminate\Database\Eloquent\Model;

class OdkAuditLogger
{
    public function record(string $action, ?Model $subject = null, array $metadata = [], ?OdkWorkspace $workspace = null, ?OdkUser $actor = null): OdkAuditLog
    {
        $request = app()->bound('request') ? request() : null;
        $actor ??= $request?->user('sanctum') ?? $request?->user();
        $workspace ??= app(OdkWorkspaceContext::class)->current();

        return OdkAuditLog::create([
            'workspace_id' => $workspace?->id,
            'actor_user_id' => $actor instanceof OdkUser ? $actor->id : null,
            'action' => $action,
            'auditable_type' => $subject?->getMorphClass(),
            'auditable_id' => $subject?->getKey(),
            'metadata' => $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
