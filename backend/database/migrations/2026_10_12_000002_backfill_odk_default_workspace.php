<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $adminUserId = DB::table('odk_users')
            ->join('odk_roles', 'odk_roles.id', '=', 'odk_users.odk_role_id')
            ->where('odk_roles.key', 'admin')
            ->orderBy('odk_users.id')
            ->value('odk_users.id');

        $workspaceId = DB::table('odk_workspaces')->where('slug', 'datakit')->value('id');

        if (!$workspaceId) {
            $workspaceId = DB::table('odk_workspaces')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'slug' => 'datakit',
                'name' => 'DataKit',
                'type' => 'organization',
                'status' => 'active',
                'owner_user_id' => $adminUserId,
                'settings' => json_encode(['legacy_default' => true]),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } elseif ($adminUserId) {
            DB::table('odk_workspaces')->where('id', $workspaceId)->update([
                'owner_user_id' => $adminUserId,
                'updated_at' => $now,
            ]);
        }

        $users = DB::table('odk_users')
            ->leftJoin('odk_roles', 'odk_roles.id', '=', 'odk_users.odk_role_id')
            ->select('odk_users.id', 'odk_roles.key as role_key')
            ->get();

        foreach ($users as $user) {
            DB::table('odk_workspace_members')->updateOrInsert(
                [
                    'odk_workspace_id' => $workspaceId,
                    'odk_user_id' => $user->id,
                ],
                [
                    'role_key' => $user->role_key === 'admin' ? 'owner' : 'member',
                    'status' => 'active',
                    'joined_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        foreach (['assessment_forms', 'assessment_exercises', 'assessment_assignments', 'assessment_submissions', 'odk_teams'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'workspace_id')) {
                DB::table($tableName)->whereNull('workspace_id')->update(['workspace_id' => $workspaceId]);
            }
        }
    }

    public function down(): void
    {
        // Existing records should remain attached to the legacy DataKit workspace on rollback.
    }
};
