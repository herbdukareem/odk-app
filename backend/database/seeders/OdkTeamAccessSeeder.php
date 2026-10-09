<?php

namespace Database\Seeders;

use App\Models\OdkRole;
use App\Models\OdkTeam;
use App\Models\OdkUser;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OdkTeamAccessSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['key' => 'admin', 'name' => 'Admin', 'description' => 'Manage users, teams, exercises, forms, assignments and reviews.', 'sort_order' => 1],
            ['key' => 'form_manager', 'name' => 'Form manager', 'description' => 'Create and publish forms and validation rules.', 'sort_order' => 2],
            ['key' => 'coordinator', 'name' => 'QA coordinator', 'description' => 'Plan exercises and assign assessors or teams.', 'sort_order' => 3],
            ['key' => 'team_lead', 'name' => 'Team lead', 'description' => 'Coordinate assigned field assessment team members.', 'sort_order' => 4],
            ['key' => 'assessor', 'name' => 'Assessor', 'description' => 'Conduct assigned facility assessments on mobile.', 'sort_order' => 5],
            ['key' => 'reviewer', 'name' => 'Reviewer', 'description' => 'Review submissions, return corrections and approve outcomes.', 'sort_order' => 6],
        ];

        foreach ($roles as $role) {
            OdkRole::updateOrCreate(
                ['key' => $role['key']],
                $role + ['permissions' => $this->permissionsFor($role['key'])]
            );
        }

        $users = [
            [
                'name' => 'Amina Yusuf',
                'email' => 'amina.yusuf@health.gov.ng',
                'role' => 'admin',
                'designation' => 'National QA Officer',
                'assigned_lga' => null,
            ],
            [
                'name' => 'Musa Bello',
                'email' => 'musa.bello@health.gov.ng',
                'role' => 'reviewer',
                'designation' => 'QA Reviewer',
                'assigned_lga' => 'Chanchaga',
            ],
            [
                'name' => 'Zainab Ahmed',
                'email' => 'zainab.ahmed@health.gov.ng',
                'role' => 'team_lead',
                'designation' => 'State QA Team Lead',
                'assigned_lga' => 'Bosso',
            ],
            [
                'name' => 'Sani Ibrahim',
                'email' => 'sani.ibrahim@health.gov.ng',
                'role' => 'assessor',
                'designation' => 'Facility Assessor',
                'assigned_lga' => 'Rafi',
            ],
            [
                'name' => 'Fatima Ali',
                'email' => 'fatima.ali@health.gov.ng',
                'role' => 'assessor',
                'designation' => 'Facility Assessor',
                'assigned_lga' => 'Shiroro',
            ],
        ];

        $userModels = [];
        foreach ($users as $user) {
            $role = OdkRole::where('key', $user['role'])->first();
            $userModels[$user['email']] = OdkUser::updateOrCreate(
                ['email' => $user['email']],
                [
                    'uuid' => OdkUser::where('email', $user['email'])->value('uuid') ?? (string) Str::uuid(),
                    'odk_role_id' => $role?->id,
                    'name' => $user['name'],
                    'phone' => null,
                    'staff_code' => strtoupper(Str::before($user['email'], '@')),
                    'designation' => $user['designation'],
                    'status' => OdkUser::STATUS_ACTIVE,
                    'assigned_lga' => $user['assigned_lga'],
                    'assigned_ward' => null,
                    'password' => Hash::make('password'),
                ]
            );
        }

        $team = OdkTeam::updateOrCreate(
            ['name' => 'Niger State QA Team A'],
            [
                'uuid' => OdkTeam::where('name', 'Niger State QA Team A')->value('uuid') ?? (string) Str::uuid(),
                'lead_user_id' => $userModels['zainab.ahmed@health.gov.ng']->id ?? null,
                'description' => 'Default QA field team for pilot facility assessments.',
                'status' => OdkTeam::STATUS_ACTIVE,
                'assigned_lga' => 'Bosso',
                'settings' => ['pilot' => true],
            ]
        );

        foreach (['zainab.ahmed@health.gov.ng' => 'lead', 'sani.ibrahim@health.gov.ng' => 'member', 'fatima.ali@health.gov.ng' => 'member'] as $email => $memberRole) {
            if (!isset($userModels[$email])) {
                continue;
            }

            $team->members()->syncWithoutDetaching([
                $userModels[$email]->id => [
                    'member_role' => $memberRole,
                    'joined_at' => now(),
                ],
            ]);
        }
    }

    private function permissionsFor(string $role): array
    {
        return match ($role) {
            'admin' => ['*'],
            'form_manager' => ['forms.manage', 'forms.publish'],
            'coordinator' => ['exercises.manage', 'assignments.manage', 'teams.view'],
            'team_lead' => ['assignments.view', 'teams.view', 'mobile.sync'],
            'assessor' => ['mobile.assessments', 'mobile.sync'],
            'reviewer' => ['submissions.review', 'reports.view'],
            default => [],
        };
    }
}
