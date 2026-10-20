<?php

namespace Tests\Feature;

use App\Models\AssessmentForm;
use App\Models\AssessmentFormField;
use App\Models\OdkRole;
use App\Models\OdkUser;
use App\Models\OdkWorkspace;
use App\Services\Forms\FormBuilderSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class FormMaximumScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_maximum_sums_the_best_option_of_each_contributing_field(): void
    {
        $form = $this->form();
        $this->field($form, 'first', [2.5, 1, 0]);
        $this->field($form, 'second', [3.75, 2, null]);
        $this->field($form, 'not_scored', [100], false);
        $this->field($form, 'legacy_scoring', [1], null);
        $this->field($form, 'signature', [50], true, 'signature');
        $this->field($form, 'deleted', [50])->delete();

        $snapshot = app(FormBuilderSnapshotService::class)->build($form);
        $this->assertSame(7.25, $snapshot['metadata_schema']['scoring_max_score']);
    }

    public function test_empty_and_unscored_options_contribute_zero_and_disabled_scoring_is_null(): void
    {
        $form = $this->form();
        $this->field($form, 'empty', []);
        $this->field($form, 'no_scores', [null, null]);
        $this->field($form, 'penalty_only', [-2, -1]);
        $service = app(FormBuilderSnapshotService::class);
        $service->synchronizeScoringMaximum($form);
        $this->assertSame('0.00', $form->fresh()->scoring_max_score);

        $form->update(['requires_scoring' => false]);
        $service->synchronizeScoringMaximum($form);
        $this->assertNull($form->fresh()->scoring_max_score);
        $this->assertSame([], $service->build($form->fresh())['scoring_rules']);
    }

    public function test_publishing_uses_calculated_maximum_without_rewriting_older_versions(): void
    {
        $form = $this->form();
        $field = $this->field($form, 'question', [2, 0]);
        $service = app(FormBuilderSnapshotService::class);
        $first = $service->publish($form);
        $this->assertSame('2.00', $first->scoring_max_score);
        $this->assertSame(2.0, (float) $first->metadata_schema['scoring_max_score']);

        $field->options()->firstOrFail()->update(['score' => 5]);
        $second = $service->publish($form->fresh());
        $this->assertSame('5.00', $second->scoring_max_score);
        $this->assertSame(5.0, (float) $second->metadata_schema['scoring_max_score']);
        $this->assertSame('5.00', $form->fresh()->scoring_max_score);
        $this->assertSame('2.00', $first->fresh()->scoring_max_score);
        $this->assertSame(2.0, (float) $first->fresh()->metadata_schema['scoring_max_score']);
    }

    public function test_creating_and_saving_forms_ignores_manual_maximum_values(): void
    {
        $this->authenticateOwner();
        $form = $this->postJson('/api/forms', ['title' => 'Automatic score', 'requires_scoring' => true, 'scoring_max_score' => 999])
            ->assertCreated()->assertJsonPath('data.scoring_max_score', '0.00')->json('data');
        $model = AssessmentForm::findOrFail($form['id']);
        $this->field($model, 'question', [3, 0]);
        $url = '/api/forms/'.$form['slug'];
        $this->getJson($url)->assertOk()->assertJsonPath('data.scoring_max_score', '3.00');
        $this->patchJson($url, ['title' => 'Automatic score', 'requires_scoring' => true, 'scoring_max_score' => 500])
            ->assertOk()->assertJsonPath('data.scoring_max_score', '3.00');
        $this->patchJson($url, ['title' => 'Renamed without scoring change'])
            ->assertOk()->assertJsonPath('data.requires_scoring', true)->assertJsonPath('data.scoring_max_score', '3.00');
        $this->patchJson($url, ['title' => 'Automatic score', 'requires_scoring' => false])
            ->assertOk()->assertJsonPath('data.scoring_max_score', null);
        $this->patchJson($url, ['title' => 'Automatic score', 'requires_scoring' => true])
            ->assertOk()->assertJsonPath('data.scoring_max_score', '3.00');
    }

    public function test_field_and_section_changes_recalculate_draft_maximum_immediately(): void
    {
        $this->authenticateOwner();
        $form = $this->postJson('/api/forms', ['title' => 'Editable score', 'requires_scoring' => true])->assertCreated()->json('data');
        $url = '/api/forms/'.$form['slug'];
        $payload = ['label' => 'Question', 'type' => 'select', 'settings' => ['scorable' => true], 'options' => [
            ['label' => 'Yes', 'value' => 'yes', 'score' => 2], ['label' => 'No', 'value' => 'no', 'score' => 0],
        ]];
        $field = $this->postJson($url.'/fields', $payload)->assertCreated()->json('data');
        $this->assertSame('2.00', AssessmentForm::findOrFail($form['id'])->scoring_max_score);
        $payload['options'][0]['score'] = 4;
        $this->patchJson($url.'/fields/'.$field['id'], $payload)->assertOk();
        $this->assertSame('4.00', AssessmentForm::findOrFail($form['id'])->scoring_max_score);
        $payload['settings']['scorable'] = false;
        $this->patchJson($url.'/fields/'.$field['id'], $payload)->assertOk();
        $this->assertSame('0.00', AssessmentForm::findOrFail($form['id'])->scoring_max_score);
        $payload['settings']['scorable'] = true;
        $this->patchJson($url.'/fields/'.$field['id'], $payload)->assertOk();
        $this->deleteJson($url.'/fields/'.$field['id'])->assertOk();
        $this->assertSame('0.00', AssessmentForm::findOrFail($form['id'])->scoring_max_score);

        $section = $this->postJson($url.'/sections', ['label' => 'Scored section'])->assertCreated()->json('data');
        $this->postJson($url.'/fields', [...$payload, 'label' => 'Section question', 'section_key' => $section['key']])->assertCreated();
        $this->assertSame('4.00', AssessmentForm::findOrFail($form['id'])->scoring_max_score);
        $this->deleteJson($url.'/sections/'.$section['id'])->assertOk();
        $this->assertSame('0.00', AssessmentForm::findOrFail($form['id'])->scoring_max_score);
    }

    public function test_clones_recalculate_instead_of_copying_a_stale_maximum(): void
    {
        $this->authenticateOwner();
        $form = $this->postJson('/api/forms', ['title' => 'Clone source', 'requires_scoring' => true])->assertCreated()->json('data');
        $model = AssessmentForm::findOrFail($form['id']);
        $this->field($model, 'question', [2.25, 0]);
        $model->update(['scoring_max_score' => 900]);
        $this->postJson('/api/forms/'.$form['slug'].'/clone')->assertCreated()->assertJsonPath('data.scoring_max_score', '2.25');
    }

    private function form(): AssessmentForm
    {
        return AssessmentForm::create(['uuid' => (string) Str::uuid(), 'slug' => 'maximum-score', 'title' => 'Maximum score', 'status' => 'draft', 'requires_scoring' => true, 'scoring_max_score' => 125]);
    }

    private function field(AssessmentForm $form, string $key, array $scores, ?bool $scorable = true, string $type = 'select'): AssessmentFormField
    {
        $field = $form->fields()->create(['key' => $key, 'label' => $key, 'type' => $type, 'settings' => $scorable === null ? null : ['scorable' => $scorable]]);
        foreach ($scores as $index => $score) {
            $field->options()->create(['label' => 'Option '.$index, 'value' => (string) $index, 'score' => $score, 'sort_order' => $index]);
        }
        return $field;
    }

    private function authenticateOwner(): void
    {
        $role = OdkRole::firstOrCreate(['key' => 'member'], ['name' => 'Member', 'permissions' => [], 'sort_order' => 1]);
        $user = OdkUser::create(['uuid' => (string) Str::uuid(), 'odk_role_id' => $role->id, 'name' => 'Owner', 'email' => 'score-owner@example.com', 'status' => 'active', 'password' => Hash::make('password')]);
        $workspace = OdkWorkspace::create(['uuid' => (string) Str::uuid(), 'slug' => 'scoring-workspace', 'name' => 'Scoring workspace', 'type' => OdkWorkspace::TYPE_ORGANIZATION, 'status' => OdkWorkspace::STATUS_ACTIVE, 'owner_user_id' => $user->id]);
        $workspace->users()->attach($user->id, ['role_key' => 'owner', 'status' => 'active', 'joined_at' => now()]);
        $this->withToken($user->createToken('test')->plainTextToken);
    }
}
