<?php

namespace App\Http\Controllers\Api;

use App\Models\OdkRole;
use App\Models\OdkTeam;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Support\OdkWorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class OdkTeamAccessController
{
    public function index(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        return response()->json([
            'success' => true,
            'data' => [
                'roles' => OdkRole::query()->orderBy('sort_order')->get(),
                'users' => $this->usersForCurrentWorkspace(),
                'teams' => OdkTeam::query()
                    ->with(['lead:id,name,email', 'members.role'])
                    ->withCount('members')
                    ->orderBy('name')
                    ->get(),
            ],
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:odk_users,email'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_key' => ['required', 'string', 'exists:odk_roles,key'],
            'staff_code' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:255'],
            'assigned_lga' => ['nullable', 'string', 'max:255'],
            'assigned_ward' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([OdkUser::STATUS_ACTIVE, OdkUser::STATUS_INACTIVE, OdkUser::STATUS_SUSPENDED])],
        ]);

        $role = OdkRole::where('key', $data['role_key'])->firstOrFail();
        $user = OdkUser::create([
            'uuid' => (string) Str::uuid(),
            'odk_role_id' => $role->id,
            'name' => $data['name'] ?? (string) Str::of($data['email'])->before('@')->replace('.', ' ')->title(),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'staff_code' => $data['staff_code'] ?? null,
            'designation' => $data['designation'] ?? null,
            'assigned_lga' => $data['assigned_lga'] ?? null,
            'assigned_ward' => $data['assigned_ward'] ?? null,
            'status' => $data['status'] ?? OdkUser::STATUS_ACTIVE,
            'password' => Hash::make($data['password']),
        ]);

        if ($workspace = $this->currentWorkspace()) {
            $workspace->users()->attach($user->id, [
                'role_key' => $data['role_key'] === 'admin' ? 'admin' : 'member',
                'status' => 'active',
                'joined_at' => now(),
            ]);
        }

        return response()->json(['success' => true, 'data' => $user->load(['role', 'teams'])], 201);
    }

    public function updateUser(Request $request, OdkUser $user): JsonResponse
    {
        $this->assertAdmin($request);
        $this->assertWorkspaceUser($user);

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('odk_users', 'email')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'role_key' => ['required', 'string', 'exists:odk_roles,key'],
            'staff_code' => ['nullable', 'string', 'max:100'],
            'designation' => ['nullable', 'string', 'max:255'],
            'assigned_lga' => ['nullable', 'string', 'max:255'],
            'assigned_ward' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([OdkUser::STATUS_ACTIVE, OdkUser::STATUS_INACTIVE, OdkUser::STATUS_SUSPENDED])],
        ]);

        $role = OdkRole::where('key', $data['role_key'])->firstOrFail();
        $values = [
            'odk_role_id' => $role->id,
            'name' => $data['name'] ?? (string) Str::of($data['email'])->before('@')->replace('.', ' ')->title(),
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'staff_code' => $data['staff_code'] ?? null,
            'designation' => $data['designation'] ?? null,
            'assigned_lga' => $data['assigned_lga'] ?? null,
            'assigned_ward' => $data['assigned_ward'] ?? null,
            'status' => $data['status'],
        ];

        $user->fill($values)->save();

        return response()->json(['success' => true, 'data' => $user->fresh(['role', 'teams'])]);
    }

    public function resetPassword(Request $request, OdkUser $user): JsonResponse
    {
        $this->assertAdmin($request);
        $this->assertWorkspaceUser($user);

        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min(8)->letters()->mixedCase()->numbers()->symbols()],
        ]);

        $user->forceFill(['password' => Hash::make($data['password'])])->save();
        $user->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'User password reset successfully.',
        ]);
    }

    public function storeTeam(Request $request): JsonResponse
    {
        $this->assertAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->teamNameRule()],
            'description' => ['nullable', 'string'],
            'lead_user_id' => ['nullable', 'integer', 'exists:odk_users,id'],
            'assigned_lga' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([OdkTeam::STATUS_ACTIVE, OdkTeam::STATUS_INACTIVE])],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'exists:odk_users,id'],
        ]);

        $team = OdkTeam::create([
            'uuid' => (string) Str::uuid(),
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'lead_user_id' => $data['lead_user_id'] ?? null,
            'assigned_lga' => $data['assigned_lga'] ?? null,
            'status' => $data['status'] ?? OdkTeam::STATUS_ACTIVE,
        ]);

        $this->syncMembers($team, $data['member_ids'] ?? [], $data['lead_user_id'] ?? null);

        return response()->json(['success' => true, 'data' => $team->fresh(['lead', 'members.role'])->loadCount('members')], 201);
    }

    public function updateTeam(Request $request, OdkTeam $team): JsonResponse
    {
        $this->assertAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255', $this->teamNameRule($team->id)],
            'description' => ['nullable', 'string'],
            'lead_user_id' => ['nullable', 'integer', 'exists:odk_users,id'],
            'assigned_lga' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in([OdkTeam::STATUS_ACTIVE, OdkTeam::STATUS_INACTIVE])],
            'member_ids' => ['array'],
            'member_ids.*' => ['integer', 'exists:odk_users,id'],
        ]);

        $team->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'lead_user_id' => $data['lead_user_id'] ?? null,
            'assigned_lga' => $data['assigned_lga'] ?? null,
            'status' => $data['status'],
        ])->save();

        $this->syncMembers($team, $data['member_ids'] ?? [], $data['lead_user_id'] ?? null);

        return response()->json(['success' => true, 'data' => $team->fresh(['lead', 'members.role'])->loadCount('members')]);
    }

    private function assertAdmin(Request $request): void
    {
        abort_unless(
            $request->user() instanceof OdkUser
                && app(OdkWorkspaceContext::class)->userCanManage($request->user()),
            403,
            'Only workspace owners and admins can manage team access.'
        );
    }

    private function syncMembers(OdkTeam $team, array $memberIds, ?int $leadUserId): void
    {
        $ids = collect($memberIds)
            ->when($leadUserId !== null, fn ($items) => $items->push($leadUserId))
            ->filter()
            ->unique()
            ->values();

        if ($workspace = $this->currentWorkspace()) {
            $allowed = $workspace->users()
                ->wherePivot('status', 'active')
                ->whereIn('odk_users.id', $ids)
                ->pluck('odk_users.id');
            abort_unless($allowed->count() === $ids->count(), 422, 'All team members must belong to the active workspace.');
        }

        $team->members()->sync($ids->mapWithKeys(fn ($id) => [
            $id => [
                'member_role' => (int) $id === (int) $leadUserId ? 'lead' : 'member',
                'joined_at' => now(),
            ],
        ])->all());
    }

    private function usersForCurrentWorkspace()
    {
        $workspace = $this->currentWorkspace();

        if (!$workspace) {
            return OdkUser::query()
                ->with(['role', 'teams:id,name,status'])
                ->orderBy('name')
                ->get();
        }

        return $workspace->users()
            ->wherePivot('status', 'active')
            ->with(['role', 'teams:id,name,status'])
            ->orderBy('name')
            ->get();
    }

    private function currentWorkspace(): ?OdkWorkspace
    {
        $workspace = app(OdkWorkspaceContext::class)->current();

        return $workspace instanceof OdkWorkspace ? $workspace : null;
    }

    private function assertWorkspaceUser(OdkUser $user): void
    {
        $workspace = $this->currentWorkspace();

        if (!$workspace) {
            return;
        }

        abort_unless(
            $workspace->users()->whereKey($user->id)->wherePivot('status', 'active')->exists(),
            404,
            'The user is not a member of the active workspace.'
        );
    }

    private function teamNameRule(?int $ignoreId = null)
    {
        $rule = Rule::unique('odk_teams', 'name');
        $workspace = $this->currentWorkspace();

        if ($workspace) {
            $rule->where(fn ($query) => $query->where('workspace_id', $workspace->id));
        }

        if ($ignoreId) {
            $rule->ignore($ignoreId);
        }

        return $rule;
    }
}
