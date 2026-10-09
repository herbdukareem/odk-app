<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(OdkTeamAccessSeeder::class);
        $this->call(NicareQaChecklistSeeder::class);
        $this->call(AssessmentSubmissionSeeder::class);
    }
}
