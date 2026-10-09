<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_submissions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('assessment_id')->unique();
            $table->string('client_submission_id')->nullable()->unique();
            $table->foreignId('assessment_form_id');
            $table->foreignId('assessment_form_version_id')->nullable();
            $table->foreignId('reference_facility_id')->nullable();
            $table->string('facility_external_id')->nullable()->index();
            $table->string('facility_name');
            $table->string('facility_code')->nullable();
            $table->string('facility_type')->nullable();
            $table->string('lga_name')->nullable()->index();
            $table->string('ward_name')->nullable()->index();
            $table->string('assessor_id')->nullable()->index();
            $table->string('assessor_name')->nullable();
            $table->date('assessment_date')->nullable();
            $table->string('source', 40)->default('mobile')->index();
            $table->string('status', 40)->default('draft')->index();
            $table->string('sync_status', 40)->default('pending')->index();
            $table->decimal('score_total', 10, 2)->nullable();
            $table->json('score_details')->nullable();
            $table->decimal('gps_latitude', 10, 7)->nullable();
            $table->decimal('gps_longitude', 10, 7)->nullable();
            $table->decimal('gps_accuracy_meters', 10, 2)->nullable();
            $table->json('facility_snapshot')->nullable();
            $table->json('assessor_snapshot')->nullable();
            $table->json('device_snapshot')->nullable();
            $table->json('answers_snapshot')->nullable();
            $table->json('validation_result')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('assessment_submissions', function (Blueprint $table): void {
            $table->foreign('assessment_form_id', 'sub_form_fk')->references('id')->on('assessment_forms')->restrictOnDelete();
            $table->foreign('assessment_form_version_id', 'sub_form_version_fk')->references('id')->on('assessment_form_versions')->nullOnDelete();
            $table->foreign('reference_facility_id', 'sub_facility_fk')->references('id')->on('reference_facilities')->nullOnDelete();
        });

        Schema::create('assessment_submission_answers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_submission_id');
            $table->string('field_key')->index();
            $table->string('section_key')->nullable()->index();
            $table->unsignedInteger('repeat_index')->nullable();
            $table->json('value')->nullable();
            $table->decimal('score', 10, 2)->nullable();
            $table->boolean('requires_review')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['assessment_submission_id', 'field_key', 'repeat_index'], 'submission_answers_field_repeat_unique');
        });

        Schema::table('assessment_submission_answers', function (Blueprint $table): void {
            $table->foreign('assessment_submission_id', 'sub_answers_submission_fk')->references('id')->on('assessment_submissions')->cascadeOnDelete();
        });

        Schema::create('assessment_submission_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_submission_id');
            $table->string('field_key')->nullable()->index();
            $table->string('type', 40)->index();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('assessment_submission_attachments', function (Blueprint $table): void {
            $table->foreign('assessment_submission_id', 'sub_attachments_submission_fk')->references('id')->on('assessment_submissions')->cascadeOnDelete();
        });

        Schema::create('assessment_submission_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_submission_id');
            $table->string('action', 60)->index();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40)->nullable();
            $table->string('reviewer_id')->nullable();
            $table->string('reviewer_name')->nullable();
            $table->text('note')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::table('assessment_submission_reviews', function (Blueprint $table): void {
            $table->foreign('assessment_submission_id', 'sub_reviews_submission_fk')->references('id')->on('assessment_submissions')->cascadeOnDelete();
        });

        Schema::create('assessment_sync_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_submission_id')->nullable();
            $table->string('client_submission_id')->nullable()->index();
            $table->string('direction', 40)->default('upload')->index();
            $table->string('status', 40)->default('pending')->index();
            $table->string('reference_id')->nullable()->index();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('attempted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('assessment_sync_events', function (Blueprint $table): void {
            $table->foreign('assessment_submission_id', 'sync_events_submission_fk')->references('id')->on('assessment_submissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_sync_events');
        Schema::dropIfExists('assessment_submission_reviews');
        Schema::dropIfExists('assessment_submission_attachments');
        Schema::dropIfExists('assessment_submission_answers');
        Schema::dropIfExists('assessment_submissions');
    }
};
