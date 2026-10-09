<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odk_roles', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('odk_users', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('odk_role_id')->nullable()->constrained('odk_roles')->nullOnDelete();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('staff_code')->nullable()->index();
            $table->string('designation')->nullable();
            $table->string('status', 40)->default('active')->index();
            $table->string('assigned_lga')->nullable()->index();
            $table->string('assigned_ward')->nullable()->index();
            $table->timestamp('last_login_at')->nullable();
            $table->json('device_info')->nullable();
            $table->rememberToken();
            $table->string('password')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('odk_teams', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('lead_user_id')->nullable()->constrained('odk_users')->nullOnDelete();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->string('status', 40)->default('active')->index();
            $table->string('assigned_lga')->nullable()->index();
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('odk_team_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('odk_team_id')->constrained('odk_teams')->cascadeOnDelete();
            $table->foreignId('odk_user_id')->constrained('odk_users')->cascadeOnDelete();
            $table->string('member_role', 40)->default('member');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['odk_team_id', 'odk_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('odk_team_members');
        Schema::dropIfExists('odk_teams');
        Schema::dropIfExists('odk_users');
        Schema::dropIfExists('odk_roles');
    }
};
