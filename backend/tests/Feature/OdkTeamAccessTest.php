<?php

namespace Tests\Feature;

use App\Models\OdkRole;
use App\Models\OdkUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OdkTeamAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_access_requires_an_authenticated_admin(): void
    {
        $this->getJson('/api/team-access')->assertUnauthorized();

        $assessor = $this->createUser('assessor');
        Sanctum::actingAs($assessor);

        $this->getJson('/api/team-access')->assertForbidden();
    }

    public function test_admin_can_edit_reset_and_impersonate_an_odk_user(): void
    {
        $admin = $this->createUser('admin');
        $target = $this->createUser('assessor', 'assessor@example.com');
        Sanctum::actingAs($admin);

        $this->patchJson('/api/team-access/users/' . $target->id, [
            'name' => 'Updated Assessor',
            'email' => $target->email,
            'phone' => '08000000000',
            'staff_code' => 'UPDATED-001',
            'role_key' => 'assessor',
            'designation' => 'Facility Assessor',
            'assigned_lga' => 'Bosso',
            'assigned_ward' => 'Ward 1',
            'status' => OdkUser::STATUS_ACTIVE,
        ])->assertOk()->assertJsonPath('data.name', 'Updated Assessor');

        $this->patchJson('/api/team-access/users/' . $target->id . '/password', [
            'password' => 'NewPassword!123',
            'password_confirmation' => 'NewPassword!123',
        ])->assertOk();

        $target->refresh();
        $this->assertTrue(Hash::check('NewPassword!123', $target->password));

        $response = $this->postJson('/api/team-access/users/' . $target->id . '/impersonate')
            ->assertOk()
            ->assertJsonPath('data.user.id', $target->id);

        $impersonationToken = $response->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->withToken($impersonationToken)
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.id', $target->id);

        $this->withToken($impersonationToken)
            ->postJson('/api/auth/stop-impersonation')
            ->assertOk();
    }

    private function createUser(string $roleKey, string $email = 'admin@example.com'): OdkUser
    {
        $role = OdkRole::create([
            'key' => $roleKey,
            'name' => ucfirst($roleKey),
            'description' => null,
            'permissions' => $roleKey === 'admin' ? ['*'] : [],
            'sort_order' => 1,
        ]);

        return OdkUser::create([
            'uuid' => fake()->uuid(),
            'odk_role_id' => $role->id,
            'name' => ucfirst($roleKey) . ' User',
            'email' => $email,
            'status' => OdkUser::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);
    }
}
