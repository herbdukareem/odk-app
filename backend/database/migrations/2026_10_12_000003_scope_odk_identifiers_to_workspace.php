<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->dropUnique('assessment_forms_slug_unique');
            $table->unique(['workspace_id', 'slug'], 'assessment_forms_workspace_slug_unique');
        });

        Schema::table('assessment_exercises', function (Blueprint $table): void {
            $table->dropUnique('assessment_exercises_code_unique');
            $table->unique(['workspace_id', 'code'], 'assessment_exercises_workspace_code_unique');
        });

        Schema::table('odk_teams', function (Blueprint $table): void {
            $table->dropUnique('odk_teams_name_unique');
            $table->unique(['workspace_id', 'name'], 'odk_teams_workspace_name_unique');
        });
    }

    public function down(): void
    {
        Schema::table('odk_teams', function (Blueprint $table): void {
            $table->dropUnique('odk_teams_workspace_name_unique');
            $table->unique('name');
        });

        Schema::table('assessment_exercises', function (Blueprint $table): void {
            $table->dropUnique('assessment_exercises_workspace_code_unique');
            $table->unique('code');
        });

        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->dropUnique('assessment_forms_workspace_slug_unique');
            $table->unique('slug');
        });
    }
};
