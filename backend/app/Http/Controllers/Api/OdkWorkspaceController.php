<?php

namespace App\Http\Controllers\Api;

use App\Mail\OdkWorkspaceInvitationMail;
use App\Models\OdkRole;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Models\OdkWorkspaceInvitation;
use App\Services\Saas\OdkSubscriptionService;
use App\Support\OdkWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Throwable;

class OdkWorkspaceController
{
    public function register(Request $request, OdkSubscriptionService $subscriptions): JsonResponse
    {
        $data = $request->validate([
            'workspace_name' => ['required', 'string', 'max:120'],
            'workspace_type' => ['required', Rule::in([OdkWorkspace::TYPE_INDIVIDUAL, OdkWorkspace::TYPE_ORGANIZATION])],
            'workspace_slug' => ['nullable', 'string', 'alpha_dash', 'min:3', 'max:80', 'unique:odk_workspaces,slug'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:odk_users,email'],
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $result = DB::transaction(function () use ($data, $subscriptions): array {
            $role = OdkRole::firstOrCreate(
                ['key' => 'admin'],
                [
                    'name' => 'Workspace administrator',
                    'description' => 'Manage the workspace and its assessment operations.',
                    'permissions' => ['*'],
                    'sort_order' => 1,
                ]
            );

            $user = OdkUser::create([
                'uuid' => (string) Str::uuid(),
                'odk_role_id' => $role->id,
                'name' => $data['name'],
                'email' => strtolower($data['email']),
                'status' => OdkUser::STATUS_ACTIVE,
                'password' => Hash::make($data['password']),
            ]);

            $workspace = OdkWorkspace::create([
                'uuid' => (string) Str::uuid(),
                'slug' => $data['workspace_slug'] ?? $this->uniqueSlug($data['workspace_name']),
                'name' => $data['workspace_name'],
                'type' => $data['workspace_type'],
                'status' => OdkWorkspace::STATUS_ACTIVE,
                'owner_user_id' => $user->id,
                'settings' => ['onboarding_complete' => false],
            ]);

            $workspace->users()->attach($user->id, [
                'role_key' => 'owner',
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $subscriptions->ensureFor($workspace);

            $token = $user->createToken('odk-web', ['workspace:' . $workspace->id])->plainTextToken;

            return compact('user', 'workspace', 'token');
        });

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $result['user']->load(['role', 'workspaces']),
                'workspace' => $result['workspace'],
                'token' => $result['token'],
            ],
        ], 201);
    }

    public function show(Request $request): JsonResponse
    {
        $workspace = $this->workspace();

        return response()->json([
            'success' => true,
            'data' => $workspace->load('owner:id,name,email')->loadCount('users'),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $workspace = $this->workspace();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'alpha_dash', 'min:3', 'max:80', Rule::unique('odk_workspaces', 'slug')->ignore($workspace->id)],
            'settings' => ['nullable', 'array'],
        ]);

        $workspace->fill($data)->save();

        return response()->json(['success' => true, 'data' => $workspace->fresh()->loadCount('users')]);
    }

    public function members(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->workspace()->users()
                ->wherePivot('status', 'active')
                ->with(['role'])
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function updateMember(Request $request, OdkUser $user): JsonResponse
    {
        $this->assertManager($request);
        $workspace = $this->workspace();
        $membership = $this->membership($workspace, $user);
        $data = $request->validate(['role_key' => [Rule::in(['admin', 'member'])]]);

        abort_if($membership->role_key === 'owner', 422, 'The workspace owner role cannot be changed.');
        $membership->update(['role_key' => $data['role_key']]);

        return response()->json(['success' => true, 'data' => $user->fresh(['role'])]);
    }

    public function removeMember(Request $request, OdkUser $user): JsonResponse
    {
        $this->assertManager($request);
        $workspace = $this->workspace();
        $membership = $this->membership($workspace, $user);

        abort_if($membership->role_key === 'owner', 422, 'The workspace owner cannot be removed.');
        $membership->update(['status' => 'removed']);

        return response()->json(['success' => true]);
    }

    public function invitations(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->workspace()->invitations()->where('status', OdkWorkspaceInvitation::STATUS_PENDING)->latest()->get(),
        ]);
    }

    public function invite(Request $request): JsonResponse
    {
        $this->assertManager($request);
        $workspace = $this->workspace();
        app(OdkSubscriptionService::class)->assertCurrentWithinLimit('members');
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role_key' => [Rule::in(['admin', 'member'])],
        ]);
        $email = strtolower($data['email']);

        abort_if($workspace->users()->where('odk_users.email', $email)->wherePivot('status', 'active')->exists(), 422, 'This person is already a workspace member.');

        $workspace->invitations()
            ->where('email', $email)
            ->where('status', OdkWorkspaceInvitation::STATUS_PENDING)
            ->update(['status' => OdkWorkspaceInvitation::STATUS_REVOKED, 'revoked_at' => now()]);

        $rawToken = Str::random(64);
        $invitation = $workspace->invitations()->create([
            'uuid' => (string) Str::uuid(),
            'invited_by_user_id' => $request->user()->id,
            'email' => $email,
            'role_key' => $data['role_key'] ?? 'member',
            'status' => OdkWorkspaceInvitation::STATUS_PENDING,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addDays(7),
        ]);

        $inviteUrl = url('/login?invite=' . $invitation->uuid . '&token=' . $rawToken);
        $emailSent = true;

        try {
            Mail::to($email)->send(new OdkWorkspaceInvitationMail(
                workspace: $workspace,
                inviteUrl: $inviteUrl,
                roleKey: $invitation->role_key,
                expiresAt: $invitation->expires_at->toFormattedDateString(),
            ));
        } catch (Throwable $exception) {
            $emailSent = false;

            Log::warning('ODK workspace invitation email could not be sent.', [
                'invitation_id' => $invitation->id,
                'workspace_id' => $workspace->id,
                'recipient' => $email,
                'exception' => $exception::class,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'invitation' => $invitation,
                'token' => $rawToken,
                'invite_url' => $inviteUrl,
                'email_sent' => $emailSent,
            ],
        ], 201);
    }

    public function revokeInvitation(Request $request, OdkWorkspaceInvitation $invitation): JsonResponse
    {
        $this->assertManager($request);
        abort_unless((int) $invitation->workspace_id === (int) $this->workspace()->id, 404);
        $invitation->update(['status' => OdkWorkspaceInvitation::STATUS_REVOKED, 'revoked_at' => now()]);

        return response()->json(['success' => true]);
    }

    public function acceptInvitation(Request $request, OdkWorkspaceInvitation $invitation): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate(['token' => ['required', 'string']]);
        abort_if($invitation->status !== OdkWorkspaceInvitation::STATUS_PENDING, 422, 'This invitation is no longer available.');
        abort_if($invitation->expires_at->isPast(), 422, 'This invitation has expired.');
        abort_unless(hash_equals($invitation->token_hash, hash('sha256', $data['token'])), 403, 'This invitation link is invalid.');
        abort_unless(strtolower((string) $user->email) === strtolower($invitation->email), 403, 'This invitation belongs to another email address.');

        DB::transaction(function () use ($invitation, $user): void {
            $invitation->workspace->users()->syncWithoutDetaching([
                $user->id => [
                    'role_key' => $invitation->role_key,
                    'status' => 'active',
                    'joined_at' => now(),
                ],
            ]);
            $invitation->update(['status' => OdkWorkspaceInvitation::STATUS_ACCEPTED, 'accepted_at' => now()]);
        });

        return response()->json(['success' => true, 'data' => $invitation->workspace->fresh()]);
    }

    private function workspace(): OdkWorkspace
    {
        $workspace = app(OdkWorkspaceContext::class)->current();
        abort_unless($workspace instanceof OdkWorkspace, 409, 'No active workspace is selected.');

        return $workspace;
    }

    private function assertManager(Request $request): void
    {
        abort_unless(app(OdkWorkspaceContext::class)->userCanManage($request->user()), 403, 'Only workspace owners and admins can manage this workspace.');
    }

    private function membership(OdkWorkspace $workspace, OdkUser $user)
    {
        return $workspace->members()
            ->where('odk_user_id', $user->id)
            ->where('status', 'active')
            ->firstOrFail();
    }

    private function uniqueSlug(string $value): string
    {
        $base = Str::slug($value) ?: 'workspace';
        $slug = $base;
        $suffix = 2;
        while (OdkWorkspace::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }
}
