<?php

namespace Database\Seeders;

use App\Models\OdkWorkspace;
use App\Support\OdkWorkspaceContext;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OdkSaasPlanSeeder::class);
        $this->call(OdkTeamAccessSeeder::class);

        $workspace = OdkWorkspace::where('slug', 'datakit')->first();
        app(OdkWorkspaceContext::class)->set($workspace);

        try {
            $this->call(NicareQaChecklistSeeder::class);
            $this->call(AssessmentSubmissionSeeder::class);
        } finally {
            app(OdkWorkspaceContext::class)->clear();
        }
    }
}
