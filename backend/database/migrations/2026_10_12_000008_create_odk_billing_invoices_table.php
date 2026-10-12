<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odk_billing_invoices', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('odk_workspaces')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('odk_workspace_subscriptions')->nullOnDelete();
            $table->foreignId('plan_id')->constrained('odk_plans');
            $table->string('provider', 40);
            $table->string('provider_reference')->nullable()->unique();
            $table->string('reference')->unique();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('NGN');
            $table->string('status', 30)->default('pending')->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_billing_invoices');
    }
};
