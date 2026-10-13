<?php

namespace App\Support;

use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\PersonalAccessToken;

class OdkWorkspaceContext
{
    private ?OdkWorkspace $workspace = null;

    private bool $resolving = false;

    public function set(?OdkWorkspace $workspace): void
    {
        $this->workspace = $workspace;
    }

    public function clear(): void
    {
        $this->workspace = null;
    }

    public function current(): ?OdkWorkspace
    {
        return $this->workspace;
    }

    public function currentMembership(?OdkUser $user = null): ?\App\Models\OdkWorkspaceMember
    {
        $workspace = $this->current();
        $user ??= request()->user('sanctum') ?? request()->user();

        if (!$workspace || !$user instanceof OdkUser) {
            return null;
        }

        return $user->workspaceMemberships()
            ->where('odk_workspace_id', $workspace->id)
            ->where('status', 'active')
            ->first();
    }

    public function userCanManage(?OdkUser $user = null): bool
    {
        $user ??= request()->user('sanctum') ?? request()->user();
        $membership = $this->currentMembership($user);

        return $membership?->role_key === 'owner'
            || $membership?->role_key === 'admin'
            || (!$membership && $user instanceof OdkUser && $user->isPlatformAdmin());
    }

    public function id(): ?int
    {
        if ($this->workspace === null && !$this->resolving && app()->bound('request')) {
            $this->resolving = true;
            try {
                $this->workspace = $this->resolve(request());
            } finally {
                $this->resolving = false;
            }
        }

        return $this->workspace?->getKey();
    }

    public function resolve(Request $request): ?OdkWorkspace
    {
        if (!Schema::hasTable('odk_workspaces')) {
            return null;
        }

        $user = $request->user('sanctum') ?? $request->user() ?? auth('sanctum')->user();
        if (!$user && $request->bearerToken()) {
            $user = PersonalAccessToken::findToken($request->bearerToken())?->tokenable;
        }

        if ($user instanceof OdkUser) {
            $requestedId = $this->requestedWorkspaceId($request, $user);
            $membership = $user->workspaces()
                ->where('odk_workspaces.status', OdkWorkspace::STATUS_ACTIVE)
                ->wherePivot('status', 'active')
                ->when($requestedId, fn ($query) => $query->where('odk_workspaces.id', $requestedId))
                ->orderBy('odk_workspaces.id')
                ->first();

            return $membership;
        }

        return OdkWorkspace::query()
            ->where('slug', 'datakit')
            ->where('status', OdkWorkspace::STATUS_ACTIVE)
            ->first();
    }

    private function requestedWorkspaceId(Request $request, OdkUser $user): ?int
    {
        $token = $user->currentAccessToken();
        $tokenWorkspace = collect($token?->abilities ?? [])
            ->first(fn ($ability) => str_starts_with((string) $ability, 'workspace:'));

        $requested = $request->header('X-Workspace-Id') ?? ($tokenWorkspace ? str_replace('workspace:', '', $tokenWorkspace) : null);

        if (!$requested) {
            return null;
        }

        return $user->workspaces()
            ->where('odk_workspaces.status', OdkWorkspace::STATUS_ACTIVE)
            ->wherePivot('status', 'active')
            ->where(function ($query) use ($requested): void {
                $query->where('odk_workspaces.id', is_numeric($requested) ? (int) $requested : 0)
                    ->orWhere('odk_workspaces.uuid', $requested)
                    ->orWhere('odk_workspaces.slug', $requested);
            })
            ->value('odk_workspaces.id');
    }
}
