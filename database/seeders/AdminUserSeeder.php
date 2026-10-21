<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('bss.seed_admin');

        if (blank($admin['phone']) || blank($admin['password'])) {
            $this->command?->warn('SEED_ADMIN_PHONE / SEED_ADMIN_PASSWORD not set: skipping the admin user.');

            return;
        }

        $user = User::updateOrCreate(
            ['phone' => $admin['phone']],
            ['name' => $admin['name'], 'password' => $admin['password']],
        );
        $user->forceFill(['phone_verified_at' => now()])->save();
        $user->syncRoles([Role::SuperAdmin->value]);
    }
}
