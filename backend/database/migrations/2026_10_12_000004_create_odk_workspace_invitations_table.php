<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odk_workspace_invitations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('workspace_id')->constrained('odk_workspaces')->cascadeOnDelete();
            $table->foreignId('invited_by_user_id')->nullable()->constrained('odk_users')->nullOnDelete();
            $table->string('email')->index();
            $table->string('role_key', 40)->default('member');
            $table->string('status', 40)->default('pending')->index();
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_workspace_invitations');
    }
};
