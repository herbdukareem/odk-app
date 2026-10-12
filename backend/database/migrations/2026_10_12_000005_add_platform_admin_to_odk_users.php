<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('odk_users', function (Blueprint $table): void {
            $table->boolean('is_platform_admin')->default(false)->after('status')->index();
        });

        DB::table('odk_users')
            ->join('odk_roles', 'odk_roles.id', '=', 'odk_users.odk_role_id')
            ->where('odk_roles.key', 'admin')
            ->update(['odk_users.is_platform_admin' => true]);
    }

    public function down(): void
    {
        Schema::table('odk_users', function (Blueprint $table): void {
            $table->dropColumn('is_platform_admin');
        });
    }
};
