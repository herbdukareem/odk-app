<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('odk_plans')) {
            Schema::create('odk_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price_amount', 12, 2)->default(0);
            $table->string('currency', 3)->default('NGN');
            $table->string('billing_interval', 20)->default('monthly');
            $table->json('limits')->nullable();
            $table->json('features')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            });
        }

        if (!Schema::hasTable('odk_workspace_subscriptions')) {
            Schema::create('odk_workspace_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('odk_workspaces')->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('odk_plans');
            $table->string('status', 30)->default('active')->index();
            $table->string('provider', 40)->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_subscription_id')->nullable();
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();

                $table->index(['provider', 'provider_subscription_id'], 'odk_ws_sub_provider_index');
            });
        } elseif (!Schema::hasIndex('odk_workspace_subscriptions', 'odk_ws_sub_provider_index')) {
            Schema::table('odk_workspace_subscriptions', function (Blueprint $table): void {
                $table->index(['provider', 'provider_subscription_id'], 'odk_ws_sub_provider_index');
            });
        }

        if (!Schema::hasTable('odk_audit_logs')) {
            Schema::create('odk_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained('odk_workspaces')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('odk_users')->nullOnDelete();
            $table->string('action', 120)->index();
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('metadata')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['workspace_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_audit_logs');
        Schema::dropIfExists('odk_workspace_subscriptions');
        Schema::dropIfExists('odk_plans');
    }
};
