<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assessment_submission_attachments', function (Blueprint $table): void {
            $table->string('client_attachment_id')->nullable();
            $table->unique(
                ['assessment_submission_id', 'client_attachment_id'],
                'sub_attachments_submission_client_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('assessment_submission_attachments', function (Blueprint $table): void {
            $table->dropUnique('sub_attachments_submission_client_unique');
            $table->dropColumn('client_attachment_id');
        });
    }
};
