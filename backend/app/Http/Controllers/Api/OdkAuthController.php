<?php

namespace App\Http\Controllers\Api;

use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Support\OdkWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OdkAuthController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = OdkUser::query()->with('role')->where('email', $credentials['email'])->first();

        if (!$user || !Hash::check($credentials['password'], (string) $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['These credentials are incorrect.'],
            ]);
        }

        if ($user->status !== OdkUser::STATUS_ACTIVE) {
            throw ValidationException::withMessages([
                'email' => ['This account is not active.'],
            ]);
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->sessionResponse($user, $user->createToken('odk-web', $this->workspaceAbilities($user))->plainTextToken);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->userPayload($request->user()),
        ]);
    }

    public function workspaces(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'current' => app(OdkWorkspaceContext::class)->current(),
                'items' => $request->user()->workspaces()
                    ->where('odk_workspaces.status', OdkWorkspace::STATUS_ACTIVE)
                    ->wherePivot('status', 'active')
                    ->orderBy('odk_workspaces.name')
                    ->get(),
            ],
        ]);
    }

    public function switchWorkspace(Request $request, OdkWorkspace $workspace): JsonResponse
    {
        abort_unless(
            $request->user()->workspaces()
                ->whereKey($workspace->id)
                ->where('odk_workspaces.status', OdkWorkspace::STATUS_ACTIVE)
                ->wherePivot('status', 'active')
                ->exists(),
            403,
            'You are not a member of this workspace.'
        );

        $token = $request->user()->currentAccessToken();
        if ($token) {
            $abilities = collect($token->abilities ?? [])
                ->reject(fn ($ability) => str_starts_with((string) $ability, 'workspace:'))
                ->push('workspace:' . $workspace->id)
                ->values()
                ->all();
            $token->forceFill(['abilities' => $abilities])->save();
        }

        return response()->json([
            'success' => true,
            'data' => $workspace,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['success' => true]);
    }

    public function impersonate(Request $request, OdkUser $user): JsonResponse
    {
        $admin = $request->user();
        $workspaceContext = app(OdkWorkspaceContext::class);

        abort_unless(
            $admin instanceof OdkUser
                && ($admin->isPlatformAdmin() || $workspaceContext->userCanManage($admin)),
            403,
            'Only platform administrators or workspace owners and admins can impersonate users.'
        );
        abort_if((int) $admin->id === (int) $user->id, 422, 'You cannot impersonate yourself.');
        abort_if($user->role()->where('key', 'admin')->exists(), 403, 'Admin accounts cannot be impersonated.');
        abort_unless($user->status === OdkUser::STATUS_ACTIVE, 422, 'Only active users can be impersonated.');

        if ($workspace = $workspaceContext->current()) {
            abort_unless(
                $workspace->users()
                    ->whereKey($user->id)
                    ->wherePivot('status', 'active')
                    ->exists(),
                404,
                'The user is not a member of the active workspace.'
            );
        }

        $abilities = array_merge([
            'impersonation',
            'impersonated-by:' . $admin->id,
        ], $this->workspaceAbilities($user));

        $token = $user->createToken(
            'odk-impersonation',
            $abilities,
            now()->addHours(8)
        )->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load('role'),
                'original_user' => $admin->load('role'),
                'token' => $token,
                'expires_at' => now()->addHours(8)->toIso8601String(),
            ],
        ]);
    }

    public function stopImpersonation(Request $request): JsonResponse
    {
        $token = $request->user()?->currentAccessToken();

        abort_unless($token && in_array('impersonation', $token->abilities ?? [], true), 400, 'You are not impersonating a user.');

        $token->delete();

        return response()->json(['success' => true]);
    }

    private function sessionResponse(OdkUser $user, string $token): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $this->userPayload($user),
                'token' => $token,
            ],
        ]);
    }

    private function userPayload(OdkUser $user): OdkUser
    {
        return $user->load(['role', 'workspaces']);
    }

    private function workspaceAbilities(OdkUser $user): array
    {
        $workspaceId = $user->workspaces()
            ->where('odk_workspaces.status', OdkWorkspace::STATUS_ACTIVE)
            ->wherePivot('status', 'active')
            ->orderBy('odk_workspaces.id')
            ->value('odk_workspaces.id');

        return $workspaceId ? ['workspace:' . $workspaceId] : [];
    }
}
