<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\EventCategory;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Default GLOBAL event categories. Safe in every environment: idempotent
        // and scoped to group_id = null so it never touches a group's own
        // categories that happen to share a name.
        $categories = [
            ['name' => 'Birth', 'icon' => '👶', 'color' => '#ec4899'],
            ['name' => 'Move', 'icon' => '🏠', 'color' => '#8b5cf6'],
            ['name' => 'Anniversary', 'icon' => '💍', 'color' => '#f59e0b'],
            ['name' => 'Graduation', 'icon' => '🎓', 'color' => '#3b82f6'],
            ['name' => 'Milestone', 'icon' => '🏆', 'color' => '#10b981'],
            ['name' => 'Wedding', 'icon' => '💒', 'color' => '#f43f5e'],
            ['name' => 'Travel', 'icon' => '✈️', 'color' => '#06b6d4'],
            ['name' => 'Career', 'icon' => '💼', 'color' => '#6366f1'],
            ['name' => 'Health', 'icon' => '🏥', 'color' => '#14b8a6'],
            ['name' => 'Other', 'icon' => '📌', 'color' => '#64748b'],
        ];

        foreach ($categories as $cat) {
            EventCategory::updateOrCreate(
                ['name' => $cat['name'], 'group_id' => null],
                $cat
            );
        }

        // ── DEV/LOCAL FIXTURES ONLY — never seed on production ──────────────
        // The default super-admin has well-known credentials (admin@family.com /
        // admin123) and DemoSeeder adds every existing user to the demo group;
        // running either against real production data would be unsafe.
        if (app()->environment('production')) {
            $this->command?->warn('Production: skipping dev fixtures (default admin + demo seed).');

            return;
        }

        // Local-only default super admin.
        User::updateOrCreate(
            ['email' => 'admin@family.com'],
            [
                'name' => 'Admin',
                'password' => bcrypt('admin123'),
                'platform_role' => 'super_admin',
            ]
        );

        $this->call(DemoSeeder::class);
    }
}
