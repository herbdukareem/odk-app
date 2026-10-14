<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\AssessmentFormField;
use App\Models\AssessmentFormSection;
use App\Models\OdkRole;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Services\Forms\FormBuilderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormFieldOrderRepairTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_field_order_is_repaired_before_mobile_snapshot_is_published(): void
    {
        $form = AssessmentForm::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'field-order-repair',
            'title' => 'Field order repair',
            'status' => AssessmentForm::STATUS_DRAFT,
        ]);
        $section = AssessmentFormSection::create([
            'assessment_form_id' => $form->id,
            'key' => 'drugs',
            'label' => 'Drugs and stocks',
            'sort_order' => 1,
        ]);
        $first = AssessmentFormField::create([
            'assessment_form_id' => $form->id,
            'assessment_form_section_id' => $section->id,
            'key' => 'iodine_available',
            'label' => 'Iodine tincture available',
            'type' => 'yes_no',
            'sort_order' => 1,
        ]);
        $second = AssessmentFormField::create([
            'assessment_form_id' => $form->id,
            'assessment_form_section_id' => $section->id,
            'key' => 'iodine_quantity',
            'label' => 'Iodine tincture quantity available',
            'type' => 'select',
            'sort_order' => 2,
        ]);

        // This is the legacy behavior caused by editing a field before the fix.
        $second->update(['sort_order' => 99]);

        $service = app(FormBuilderSnapshotService::class);
        $this->assertSame(1, $service->repairFieldOrder($form));
        $this->assertSame(1, (int) $first->fresh()->sort_order);
        $this->assertSame(2, (int) $second->fresh()->sort_order);

        $version = $service->publish($form->fresh());
        $snapshotFields = collect($version->fields);

        $this->assertSame(
            ['iodine_available', 'iodine_quantity'],
            $snapshotFields->pluck('key')->all()
        );
        $this->assertSame([1, 2], $snapshotFields->pluck('order')->all());
    }

    public function test_workspace_manager_can_reorder_fields_and_stale_orders_are_rejected(): void
    {
        $role = OdkRole::firstOrCreate(
            ['key' => 'member'],
            ['name' => 'Member', 'description' => null, 'permissions' => [], 'sort_order' => 1]
        );
        $user = OdkUser::create([
            'uuid' => (string) Str::uuid(),
            'odk_role_id' => $role->id,
            'name' => 'Workspace Owner',
            'email' => 'order-owner@example.com',
            'status' => OdkUser::STATUS_ACTIVE,
            'password' => Hash::make('password'),
        ]);
        $workspace = OdkWorkspace::create([
            'uuid' => (string) Str::uuid(),
            'slug' => 'order-workspace',
            'name' => 'Order workspace',
            'type' => OdkWorkspace::TYPE_ORGANIZATION,
            'status' => OdkWorkspace::STATUS_ACTIVE,
            'owner_user_id' => $user->id,
        ]);
        $workspace->users()->attach($user->id, [
            'role_key' => 'owner',
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $token = $user->createToken('test')->plainTextToken;
        $form = $this->withToken($token)->postJson('/api/forms', [
            'title' => 'Reorderable form',
            'requires_scoring' => false,
        ])->assertCreated()->json('data');
        $section = $this->withToken($token)->postJson('/api/forms/'.$form['slug'].'/sections', [
            'label' => 'Questions',
            'key' => 'questions',
        ])->assertCreated()->json('data');

        $first = $this->withToken($token)->postJson('/api/forms/'.$form['slug'].'/fields', [
            'label' => 'First question',
            'type' => 'text',
            'section_key' => $section['key'],
        ])->assertCreated()->json('data');
        $second = $this->withToken($token)->postJson('/api/forms/'.$form['slug'].'/fields', [
            'label' => 'Second question',
            'type' => 'text',
            'section_key' => $section['key'],
        ])->assertCreated()->json('data');

        $this->withToken($token)
            ->putJson('/api/forms/'.$form['slug'].'/fields/order', [
                'section_key' => $section['key'],
                'field_ids' => [$second['id'], $first['id']],
            ])
            ->assertOk()
            ->assertJsonPath('data.fields.0.id', $second['id'])
            ->assertJsonPath('data.fields.1.id', $first['id']);

        $this->withToken($token)
            ->putJson('/api/forms/'.$form['slug'].'/fields/order', [
                'section_key' => $section['key'],
                'field_ids' => [$first['id']],
            ])
            ->assertStatus(422);

        $this->assertSame(
            [$second['id'], $first['id']],
            AssessmentFormField::query()
                ->where('assessment_form_id', $form['id'])
                ->where('assessment_form_section_id', $section['id'])
                ->orderBy('sort_order')
                ->pluck('id')
                ->all()
        );
    }
}
