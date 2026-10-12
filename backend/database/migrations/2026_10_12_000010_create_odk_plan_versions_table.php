<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('odk_plan_versions')) {
            return;
        }

        Schema::create('odk_plan_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('plan_id')->constrained('odk_plans')->cascadeOnDelete();
            $table->foreignId('changed_by_user_id')->nullable()->constrained('odk_users')->nullOnDelete();
            $table->string('action', 30);
            $table->json('snapshot');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_plan_versions');
    }
};
