<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_forms', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('target_audience')->nullable();
            $table->string('status', 40)->default('draft')->index();
            $table->unsignedBigInteger('current_version_id')->nullable()->index();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('assessment_form_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_form_id')->constrained('assessment_forms')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 40)->default('draft')->index();
            $table->boolean('offline_enabled')->default(true);
            $table->json('metadata_schema')->nullable();
            $table->json('sections');
            $table->json('fields');
            $table->json('validation_rules')->nullable();
            $table->json('visibility_rules')->nullable();
            $table->json('scoring_rules')->nullable();
            $table->json('required_evidence_rules')->nullable();
            $table->json('signature_rules')->nullable();
            $table->json('review_flags')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['assessment_form_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_form_versions');
        Schema::dropIfExists('assessment_forms');
    }
};

