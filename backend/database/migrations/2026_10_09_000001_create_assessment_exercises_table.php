<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_exercises', function (Blueprint $table): void {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('period_type', 40)->index();
            $table->unsignedSmallInteger('year')->index();
            $table->unsignedTinyInteger('quarter')->nullable()->index();
            $table->unsignedTinyInteger('month')->nullable()->index();
            $table->date('starts_at')->index();
            $table->date('ends_at')->index();
            $table->foreignId('assessment_form_id')->constrained('assessment_forms')->restrictOnDelete();
            $table->foreignId('assessment_form_version_id')->constrained('assessment_form_versions')->restrictOnDelete();
            $table->string('status', 40)->default('draft')->index();
            $table->json('settings')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'starts_at', 'ends_at']);
            $table->index(['assessment_form_id', 'assessment_form_version_id'], 'exercise_form_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_exercises');
    }
};
