<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->boolean('requires_scoring')->default(false)->after('target_audience');
            $table->decimal('scoring_max_score', 8, 2)->nullable()->after('requires_scoring');
        });

        Schema::table('assessment_form_versions', function (Blueprint $table): void {
            $table->boolean('requires_scoring')->default(false)->after('offline_enabled');
            $table->decimal('scoring_max_score', 8, 2)->nullable()->after('requires_scoring');
        });
    }

    public function down(): void
    {
        Schema::table('assessment_form_versions', function (Blueprint $table): void {
            $table->dropColumn(['requires_scoring', 'scoring_max_score']);
        });

        Schema::table('assessment_forms', function (Blueprint $table): void {
            $table->dropColumn(['requires_scoring', 'scoring_max_score']);
        });
    }
};
