<?php

namespace App\Http\Controllers\Api;

use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OdkMobileAuthController
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = OdkUser::query()->with('role')->where('email', strtolower($credentials['email']))->first();
        if (!$user || !Hash::check($credentials['password'], (string) $user->password)) {
            throw ValidationException::withMessages(['email' => ['These credentials are incorrect.']]);
        }
        abort_if($user->status !== OdkUser::STATUS_ACTIVE, 422, 'This account is not active.');
        abort_if($user->workspaces()->wherePivot('status', 'active')->doesntExist(), 403, 'This account has no active workspace membership.');

        $workspaceId = $user->workspaces()
            ->where('odk_workspaces.status', OdkWorkspace::STATUS_ACTIVE)
            ->wherePivot('status', 'active')
            ->orderBy('odk_workspaces.id')
            ->value('odk_workspaces.id');
        abort_unless($workspaceId, 403, 'This account has no active workspace available.');
        $token = $user->createToken('odk-mobile', ['workspace:' . $workspaceId])->plainTextToken;

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user->load(['role', 'workspaces']),
                'token' => $token,
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $request->user()->load(['role', 'workspaces'])]);
    }
}
