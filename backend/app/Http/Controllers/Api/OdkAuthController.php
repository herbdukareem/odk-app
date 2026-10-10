<?php

namespace App\Http\Controllers\Api;

use App\Models\OdkUser;
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

        return $this->sessionResponse($user, $user->createToken('odk-web')->plainTextToken);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load('role'),
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

        abort_unless($admin instanceof OdkUser && $admin->isAdmin(), 403, 'Only ODK admins can impersonate users.');
        abort_if((int) $admin->id === (int) $user->id, 422, 'You cannot impersonate yourself.');
        abort_if($user->role()->where('key', 'admin')->exists(), 403, 'Admin accounts cannot be impersonated.');
        abort_unless($user->status === OdkUser::STATUS_ACTIVE, 422, 'Only active users can be impersonated.');

        $token = $user->createToken(
            'odk-impersonation',
            ['impersonation', 'impersonated-by:' . $admin->id],
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
                'user' => $user->load('role'),
                'token' => $token,
            ],
        ]);
    }
}
