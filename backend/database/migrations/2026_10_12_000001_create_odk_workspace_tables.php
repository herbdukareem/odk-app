<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odk_workspaces', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('type', 40)->default('organization')->index();
            $table->string('status', 40)->default('active')->index();
            $table->foreignId('owner_user_id')->nullable()->constrained('odk_users')->nullOnDelete();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('odk_workspace_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('odk_workspace_id')->constrained('odk_workspaces')->cascadeOnDelete();
            $table->foreignId('odk_user_id')->constrained('odk_users')->cascadeOnDelete();
            $table->string('role_key', 40)->default('member')->index();
            $table->string('status', 40)->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['odk_workspace_id', 'odk_user_id']);
        });

        foreach (['assessment_forms', 'assessment_exercises', 'assessment_assignments', 'assessment_submissions', 'odk_teams'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->foreignId('workspace_id')->nullable()->after('id')->constrained('odk_workspaces')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['assessment_submissions', 'assessment_assignments', 'assessment_exercises', 'assessment_forms', 'odk_teams'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropForeign(['workspace_id']);
                $table->dropColumn('workspace_id');
            });
        }

        Schema::dropIfExists('odk_workspace_members');
        Schema::dropIfExists('odk_workspaces');
    }
};
