<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odk_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->constrained('odk_workspaces')->cascadeOnDelete();
            $table->uuid('uuid')->unique();
            $table->string('type', 40)->index();
            $table->string('external_id')->nullable();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['workspace_id', 'type', 'external_id'], 'odk_targets_workspace_type_external_unique');
        });

        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->string('target_type', 40)->default('facility')->after('status')->index();
        });

        Schema::table('assessment_exercises', function (Blueprint $table): void {
            $table->string('target_type', 40)->default('facility')->after('status')->index();
        });

        Schema::table('assessment_assignments', function (Blueprint $table): void {
            $table->foreignId('reference_facility_id')->nullable()->change();
            $table->string('target_type', 40)->default('facility')->after('reference_facility_id')->index();
            $table->foreignId('odk_target_id')->nullable()->after('target_type')->constrained('odk_targets')->nullOnDelete();
        });

        Schema::table('assessment_submissions', function (Blueprint $table): void {
            $table->string('target_type', 40)->default('facility')->after('reference_facility_id')->index();
            $table->foreignId('odk_target_id')->nullable()->after('target_type')->constrained('odk_targets')->nullOnDelete();
            $table->json('target_snapshot')->nullable()->after('facility_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_submissions', function (Blueprint $table): void {
            $table->dropForeign(['odk_target_id']);
            $table->dropColumn(['target_type', 'odk_target_id', 'target_snapshot']);
        });

        Schema::table('assessment_assignments', function (Blueprint $table): void {
            $table->dropForeign(['odk_target_id']);
            $table->dropColumn(['target_type', 'odk_target_id']);
        });

        Schema::table('assessment_exercises', function (Blueprint $table): void {
            $table->dropColumn('target_type');
        });

        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->dropColumn('target_type');
        });

        Schema::dropIfExists('odk_targets');
    }
};
