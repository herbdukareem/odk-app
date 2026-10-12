<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('odk_plans') && !Schema::hasColumn('odk_plans', 'provider_plan_code')) {
            Schema::table('odk_plans', function (Blueprint $table): void {
                $table->string('provider_plan_code', 80)->nullable()->unique()->after('code');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('odk_plans') && Schema::hasColumn('odk_plans', 'provider_plan_code')) {
            Schema::table('odk_plans', function (Blueprint $table): void {
                $table->dropUnique(['provider_plan_code']);
                $table->dropColumn('provider_plan_code');
            });
        }
    }
};
