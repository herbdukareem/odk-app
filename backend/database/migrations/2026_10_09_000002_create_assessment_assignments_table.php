<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_assignments', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->foreignId('assessment_exercise_id')->constrained('assessment_exercises')->cascadeOnDelete();
            $table->foreignId('reference_facility_id')->constrained('reference_facilities')->restrictOnDelete();
            $table->string('assignee_type', 40)->index();
            $table->foreignId('odk_user_id')->nullable()->constrained('odk_users')->nullOnDelete();
            $table->foreignId('odk_team_id')->nullable()->constrained('odk_teams')->nullOnDelete();
            $table->string('status', 40)->default('assigned')->index();
            $table->boolean('allow_duplicate_submission')->default(false);
            $table->timestamp('assigned_at')->nullable();
            $table->date('due_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assessment_exercise_id', 'reference_facility_id'], 'assignment_exercise_facility_idx');
            $table->index(['assignee_type', 'odk_user_id', 'odk_team_id'], 'assignment_assignee_idx');
            $table->index(['status', 'due_at']);
        });

        Schema::table('assessment_submissions', function (Blueprint $table): void {
            $table->foreignId('assessment_exercise_id')->nullable()->after('assessment_form_version_id');
            $table->foreignId('assessment_assignment_id')->nullable()->after('assessment_exercise_id');

            $table->index(['assessment_exercise_id', 'reference_facility_id'], 'submission_exercise_facility_idx');
            $table->foreign('assessment_exercise_id', 'sub_exercise_fk')->references('id')->on('assessment_exercises')->nullOnDelete();
            $table->foreign('assessment_assignment_id', 'sub_assignment_fk')->references('id')->on('assessment_assignments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('assessment_submissions', function (Blueprint $table): void {
            $table->dropForeign('sub_assignment_fk');
            $table->dropForeign('sub_exercise_fk');
            $table->dropIndex('submission_exercise_facility_idx');
            $table->dropColumn(['assessment_assignment_id', 'assessment_exercise_id']);
        });

        Schema::dropIfExists('assessment_assignments');
    }
};
