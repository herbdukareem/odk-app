<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('assessment_forms')
            ->where('slug', 'nicare-qa-checklist')
            ->where('title', 'NiCare QA Checklist')
            ->update(['title' => 'DataKit QA Checklist']);
    }

    public function down(): void
    {
        DB::table('assessment_forms')
            ->where('slug', 'nicare-qa-checklist')
            ->where('title', 'DataKit QA Checklist')
            ->update(['title' => 'NiCare QA Checklist']);
    }
};
