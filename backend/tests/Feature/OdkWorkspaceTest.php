<?php

namespace Tests\Feature;

use App\Mail\OdkWorkspaceInvitationMail;
use App\Models\AssessmentExercise;
use App\Models\AssessmentForm;
use App\Models\AssessmentFormFieldOption;
use App\Models\AssessmentFormVersion;
use App\Models\OdkRole;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Support\OdkWorkspaceContext;
use App\Services\Forms\FormBuilderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class OdkWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_users_only_see_forms_in_their_workspace(): void
    {
        [$firstUser, $firstWorkspace] = $this->createWorkspaceUser('first@example.com', 'First workspace');
        [$secondUser, $secondWorkspace] = $this->createWorkspaceUser('second@example.com', 'Second workspace');

        $firstToken = $firstUser->createToken('test')->plainTextToken;
        $firstForm = $this->withToken($firstToken)->postJson('/api/forms', [
            'title' => 'First workspace form',
            'requires_scoring' => false,
        ])->assertCreated()->assertJsonPath('data.workspace_id', $firstWorkspace->id)->json('data');

        $this->app['auth']->forgetGuards();
        $secondToken = $secondUser->createToken('test')->plainTextToken;
        $this->withToken($secondToken)->postJson('/api/forms', [
            'title' => 'Second workspace form',
            'requires_scoring' => false,
        ])->assertCreated()->assertJsonPath('data.workspace_id', $secondWorkspace->id);

        $this->withToken($secondToken)->getJson('/api/forms')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Second workspace form');

        $this->withToken($secondToken)->getJson('/api/forms/' . $firstForm['slug'])->assertNotFound();

        $this->assertDatabaseHas('assessment_forms', [
            'slug' => $firstForm['slug'],
            'workspace_id' => $firstWorkspace->id,
        ]);
        $this->assertDatabaseHas('assessment_forms', [
            'title' => 'Second workspace form',
            'workspace_id' => $secondWorkspace->id,
        ]);
    }

    public function test_login_exposes_workspace_memberships(): void
    {
        [$user, $workspace] = $this->createWorkspaceUser('member@example.com', 'Member workspace');

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.workspaces.0.id', $workspace->id)
            ->assertJsonPath('data.user.workspaces.0.name', 'Member workspace');
    }

    public function test_registration_creates_a_workspace_and_owner_session(): void
    {
        $response = $this->postJson('/api/auth/register', [
            'workspace_name' => 'Acme Field Team',
            'workspace_type' => 'organization',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'password' => 'StrongPass!123',
            'password_confirmation' => 'StrongPass!123',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.workspace.name', 'Acme Field Team')
            ->assertJsonPath('data.workspace.slug', 'acme-field-team')
            ->assertJsonPath('data.user.workspaces.0.pivot.role_key', 'owner');

        $this->withToken($response->json('data.token'))
            ->getJson('/api/workspaces/current')
            ->assertOk()
            ->assertJsonPath('data.name', 'Acme Field Team')
            ->assertJsonPath('data.users_count', 1);
    }

    public function test_workspace_owner_can_invite_and_recipient_can_accept(): void
    {
        Mail::fake();

        [$owner, $workspace] = $this->createWorkspaceUser('owner@example.com', 'Owner workspace');
        $ownerToken = $owner->createToken('test')->plainTextToken;

        $invitation = $this->withToken($ownerToken)
            ->postJson('/api/workspaces/current/invitations', [
                'email' => 'invitee@example.com',
                'role_key' => 'member',
            ])
            ->assertCreated()
            ->assertJsonPath('data.invitation.workspace_id', $workspace->id)
            ->assertJsonPath('data.email_sent', true)
            ->json('data');

        Mail::assertSent(OdkWorkspaceInvitationMail::class, function (OdkWorkspaceInvitationMail $mail): bool {
            return $mail->hasTo('invitee@example.com')
                && $mail->workspace->name === 'Owner workspace'
                && $mail->roleKey === 'member';
        });

        $role = OdkRole::where('key', 'member')->firstOrFail();
        $invitee = OdkUser::create([
            'uuid' => fake()->uuid(),
            'odk_role_id' => $role->id,
            'name' => 'Invited User',
            'email' => 'invitee@example.com',
            'status' => OdkUser::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($invitee->createToken('test')->plainTextToken)
            ->postJson('/api/auth/invitations/' . $invitation['invitation']['uuid'] . '/accept', [
                'token' => $invitation['token'],
            ])
            ->assertOk()
            ->assertJsonPath('data.id', $workspace->id);

        $this->assertDatabaseHas('odk_workspace_members', [
            'odk_workspace_id' => $workspace->id,
            'odk_user_id' => $invitee->id,
            'role_key' => 'member',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('odk_workspace_invitations', [
            'uuid' => $invitation['invitation']['uuid'],
            'status' => 'accepted',
        ]);
    }

    public function test_workspace_admin_cannot_impersonate_a_user_from_another_workspace(): void
    {
        [$firstOwner] = $this->createWorkspaceUser('first-owner@example.com', 'First workspace');
        [$secondUser] = $this->createWorkspaceUser('second-user@example.com', 'Second workspace');

        $this->withToken($firstOwner->createToken('test')->plainTextToken)
            ->postJson('/api/team-access/users/' . $secondUser->id . '/impersonate')
            ->assertNotFound();
    }

    public function test_empty_canonical_form_is_restored_by_the_ensure_command(): void
    {
        $workspace = OdkWorkspace::query()->firstOrCreate(
            ['slug' => 'datakit'],
            [
                'uuid' => fake()->uuid(),
                'name' => 'DataKit',
                'type' => OdkWorkspace::TYPE_ORGANIZATION,
                'status' => OdkWorkspace::STATUS_ACTIVE,
            ]
        );
        $form = AssessmentForm::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->where('slug', 'nicare-qa-checklist')
            ->first();

        if (!$form) {
            $form = AssessmentForm::create([
                'workspace_id' => $workspace->id,
                'uuid' => fake()->uuid(),
                'slug' => 'nicare-qa-checklist',
                'title' => 'DataKit QA Checklist',
                'status' => AssessmentForm::STATUS_DRAFT,
            ]);
        }

        $form->forceFill(['status' => AssessmentForm::STATUS_DRAFT, 'current_version_id' => null])->save();
        $form->fields()->delete();
        $form->sections()->delete();
        $form->builderRules()->delete();
        $form->versions()->delete();

        Artisan::call('odk:ensure-nicare-form');

        $this->assertGreaterThan(0, $form->fresh()->sections()->count());
        $this->assertGreaterThan(0, $form->fresh()->fields()->count());
        $this->assertSame(AssessmentForm::STATUS_PUBLISHED, $form->fresh()->status);
    }

    public function test_form_field_options_can_be_replaced_without_duplicate_value_errors(): void
    {
        [$user] = $this->createWorkspaceUser('builder@example.com', 'Builder workspace');
        $token = $user->createToken('test')->plainTextToken;

        $form = $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Builder form',
            'requires_scoring' => false,
        ])->assertCreated()->json('data');

        $section = $this->withToken($token)->postJson('/api/forms/' . $form['slug'] . '/sections', [
            'label' => 'Questions',
            'key' => 'questions',
        ])->assertCreated()->json('data');

        $field = $this->withToken($token)->postJson('/api/forms/' . $form['slug'] . '/fields', [
            'label' => 'Available?',
            'type' => 'yes_no',
            'section_key' => $section['key'],
            'options' => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'Yes again', 'value' => 'yes'],
                ['label' => 'No', 'value' => 'no'],
            ],
        ])->assertCreated()->json('data');

        $this->assertCount(2, $field['options']);

        $this->withToken($token)->postJson('/api/forms/' . $form['slug'] . '/fields', [
            'label' => 'Second question',
            'type' => 'text',
            'section_key' => $section['key'],
        ])->assertCreated();

        $this->withToken($token)->patchJson('/api/forms/' . $form['slug'] . '/fields/' . $field['id'], [
            'label' => 'Available?',
            'type' => 'yes_no',
            'section_key' => $section['key'],
            'options' => [
                ['label' => 'Yes', 'value' => 'yes'],
                ['label' => 'No', 'value' => 'no'],
            ],
        ])->assertOk()
            ->assertJsonCount(2, 'data.options')
            ->assertJsonPath('data.sort_order', 1);

        $this->assertSame(
            2,
            AssessmentFormFieldOption::withTrashed()
                ->where('assessment_form_field_id', $field['id'])
                ->count()
        );
    }

    public function test_publishing_a_form_updates_open_exercises_but_preserves_closed_exercises(): void
    {
        [, $workspace] = $this->createWorkspaceUser('versioning@example.com', 'Versioning workspace');
        $context = app(OdkWorkspaceContext::class);
        $context->set($workspace);

        try {
            $form = AssessmentForm::create([
                'uuid' => fake()->uuid(),
                'slug' => 'versioned-form',
                'title' => 'Versioned form',
                'status' => AssessmentForm::STATUS_DRAFT,
            ]);
            $service = app(FormBuilderSnapshotService::class);
            $firstVersion = $service->publish($form);

            $exercises = collect([
                AssessmentExercise::STATUS_DRAFT,
                AssessmentExercise::STATUS_SCHEDULED,
                AssessmentExercise::STATUS_ACTIVE,
                AssessmentExercise::STATUS_CLOSED,
            ])->map(function (string $status) use ($form, $firstVersion): AssessmentExercise {
                return AssessmentExercise::create([
                    'uuid' => fake()->uuid(),
                    'name' => ucfirst($status).' exercise',
                    'code' => strtoupper($status).'-VERSIONED',
                    'period_type' => AssessmentExercise::PERIOD_CUSTOM,
                    'year' => (int) now()->year,
                    'starts_at' => now()->toDateString(),
                    'ends_at' => now()->addDay()->toDateString(),
                    'assessment_form_id' => $form->id,
                    'assessment_form_version_id' => $firstVersion->id,
                    'status' => $status,
                ]);
            });

            $form->forceFill(['status' => AssessmentForm::STATUS_DRAFT])->save();
            $secondVersion = $service->publish($form->fresh());

            $exercises->each(function (AssessmentExercise $exercise) use ($secondVersion): void {
                $expectedVersion = $exercise->status === AssessmentExercise::STATUS_CLOSED
                    ? $exercise->assessment_form_version_id
                    : $secondVersion->id;

                $this->assertSame($expectedVersion, $exercise->fresh()->assessment_form_version_id);
            });
        } finally {
            $context->clear();
        }
    }

    private function createWorkspaceUser(string $email, string $workspaceName): array
    {
        $role = OdkRole::firstOrCreate(
            ['key' => 'member'],
            [
                'name' => 'Member',
                'description' => null,
                'permissions' => [],
                'sort_order' => 1,
            ]
        );

        $user = OdkUser::create([
            'uuid' => fake()->uuid(),
            'odk_role_id' => $role->id,
            'name' => 'Workspace User',
            'email' => $email,
            'status' => OdkUser::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);

        $workspace = OdkWorkspace::create([
            'uuid' => fake()->uuid(),
            'slug' => str($workspaceName)->slug(),
            'name' => $workspaceName,
            'type' => OdkWorkspace::TYPE_ORGANIZATION,
            'status' => OdkWorkspace::STATUS_ACTIVE,
            'owner_user_id' => $user->id,
        ]);

        $workspace->users()->attach($user->id, [
            'role_key' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        return [$user, $workspace];
    }
}
