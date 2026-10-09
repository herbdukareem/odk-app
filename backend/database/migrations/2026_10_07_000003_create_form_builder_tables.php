<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_form_sections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_form_id')->constrained('assessment_forms')->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_repeatable')->default(false);
            $table->unsignedInteger('min_repeats')->nullable();
            $table->unsignedInteger('max_repeats')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['assessment_form_id', 'key'], 'form_sections_form_key_unique');
            $table->index(['assessment_form_id', 'sort_order'], 'form_sections_form_order_index');
        });

        Schema::create('assessment_form_fields', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_form_id')->constrained('assessment_forms')->cascadeOnDelete();
            $table->foreignId('assessment_form_section_id')->nullable()->constrained('assessment_form_sections')->nullOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type', 60);
            $table->text('help_text')->nullable();
            $table->string('placeholder')->nullable();
            $table->boolean('required')->default(false);
            $table->boolean('readonly')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('group')->nullable();
            $table->string('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('display_logic')->nullable();
            $table->json('required_logic')->nullable();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['assessment_form_id', 'key'], 'form_fields_form_key_unique');
            $table->index(['assessment_form_id', 'assessment_form_section_id', 'sort_order'], 'form_fields_form_section_order_index');
        });

        Schema::create('assessment_form_field_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_form_field_id')->constrained('assessment_form_fields')->cascadeOnDelete();
            $table->string('label');
            $table->string('value');
            $table->decimal('score', 8, 2)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('requires_review')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['assessment_form_field_id', 'value'], 'form_field_options_field_value_unique');
            $table->index(['assessment_form_field_id', 'sort_order'], 'form_field_options_field_order_index');
        });

        Schema::create('assessment_form_rules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('assessment_form_id')->constrained('assessment_forms')->cascadeOnDelete();
            $table->foreignId('assessment_form_field_id')->nullable()->constrained('assessment_form_fields')->nullOnDelete();
            $table->string('type', 60);
            $table->string('name')->nullable();
            $table->json('condition')->nullable();
            $table->json('action')->nullable();
            $table->text('message')->nullable();
            $table->string('severity', 40)->default('error');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('enabled')->default(true);
            $table->boolean('requires_review')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['assessment_form_id', 'type', 'sort_order'], 'form_rules_form_type_order_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_form_rules');
        Schema::dropIfExists('assessment_form_field_options');
        Schema::dropIfExists('assessment_form_fields');
        Schema::dropIfExists('assessment_form_sections');
    }
};
