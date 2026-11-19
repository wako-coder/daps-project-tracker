<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Demo data for the live site: one organization, two members with different
 * roles, one project with a few tasks.
 *
 * Safe to run more than once: if the demo organization's admin already exists
 * it does nothing, so a redeploy never duplicates or resets live data.
 *
 * Account passwords come from DEMO_ADMIN_PASSWORD and DEMO_MEMBER_PASSWORD;
 * nothing secret lives in the repository.
 */
class DemoSeeder extends Seeder
{
    public const ORGANIZATION = 'Entoto Builders';

    public const ADMIN_EMAIL = 'selam@entoto.example';

    public const MEMBER_EMAIL = 'dawit@entoto.example';

    public function run(): void
    {
        if (User::where('email', self::ADMIN_EMAIL)->exists()) {
            $this->command?->info('Demo data already present; nothing to do.');

            return;
        }

        $adminPassword = config('tracker.demo.admin_password');
        $memberPassword = config('tracker.demo.member_password');

        if (blank($adminPassword) || blank($memberPassword)) {
            throw new RuntimeException('Set DEMO_ADMIN_PASSWORD and DEMO_MEMBER_PASSWORD before seeding demo data.');
        }

        DB::transaction(function () use ($adminPassword, $memberPassword) {
            $admin = User::create(['name' => 'Selam Tesfaye', 'email' => self::ADMIN_EMAIL, 'password' => $adminPassword]);
            $member = User::create(['name' => 'Dawit Bekele', 'email' => self::MEMBER_EMAIL, 'password' => $memberPassword]);

            $organization = Organization::create(['name' => self::ORGANIZATION]);
            $organization->users()->attach($admin, ['role' => Role::Admin->value]);
            $organization->users()->attach($member, ['role' => Role::Member->value]);

            $project = $organization->projects()->create([
                'name' => 'Bole Office Renovation',
                'description' => 'Refit of the third-floor office: survey, demolition, electrical, finishing and furniture.',
                'budget_santim' => 2_500_000_00,
            ]);

            $project->tasks()->createMany([
                ['title' => 'Site survey and measurements', 'estimated_cost_santim' => 85_000_00, 'status' => TaskStatus::Done, 'priority' => TaskPriority::Medium],
                ['title' => 'Demolition and debris removal', 'estimated_cost_santim' => 150_000_00, 'status' => TaskStatus::Done, 'priority' => TaskPriority::Medium],
                ['title' => 'Electrical rewiring', 'estimated_cost_santim' => 420_000_00, 'status' => TaskStatus::InProgress, 'priority' => TaskPriority::High],
                ['title' => 'Interior painting', 'estimated_cost_santim' => 310_000_00, 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Low],
                ['title' => 'Furniture procurement', 'estimated_cost_santim' => 650_000_00, 'status' => TaskStatus::Todo, 'priority' => TaskPriority::Medium],
            ]);
        });

        $this->command?->info('Demo data created.');
    }
}
