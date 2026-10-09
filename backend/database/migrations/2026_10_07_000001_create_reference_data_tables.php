<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_lgas', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->string('zone')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reference_wards', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->unsignedBigInteger('source_lga_id')->nullable()->index();
            $table->foreignId('reference_lga_id')->nullable()->constrained('reference_lgas')->nullOnDelete();
            $table->string('name');
            $table->string('settlement_type')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reference_insurance_programmes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('code')->nullable()->index();
            $table->string('name');
            $table->string('type')->nullable();
            $table->boolean('active')->default(true)->index();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('reference_facilities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_id')->unique();
            $table->string('source_code')->nullable()->index();
            $table->string('name');
            $table->string('ownership')->nullable();
            $table->string('type')->nullable();
            $table->string('level_of_care')->nullable();
            $table->string('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->unsignedBigInteger('source_lga_id')->nullable()->index();
            $table->unsignedBigInteger('source_ward_id')->nullable()->index();
            $table->foreignId('reference_lga_id')->nullable()->constrained('reference_lgas')->nullOnDelete();
            $table->foreignId('reference_ward_id')->nullable()->constrained('reference_wards')->nullOnDelete();
            $table->boolean('active')->default(true)->index();
            $table->string('accreditation_status')->nullable()->index();
            $table->json('source_payload')->nullable();
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });

        Schema::create('integration_sync_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 80)->index();
            $table->string('sync_type', 80)->index();
            $table->string('status', 40)->index();
            $table->json('counts')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_sync_logs');
        Schema::dropIfExists('reference_facilities');
        Schema::dropIfExists('reference_insurance_programmes');
        Schema::dropIfExists('reference_wards');
        Schema::dropIfExists('reference_lgas');
    }
};

