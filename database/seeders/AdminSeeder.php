<?php

namespace Database\Seeders;

use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    /**
     * Seed the single internal super admin account.
     *
     * MVP: admin accounts are provisioned only here — there is no
     * registration or admin-management UI (US-ADMIN-01 AC3).
     */
    public function run(): void
    {
        Admin::query()->firstOrCreate(
            ['email' => 'admin@sparta.test'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
            ],
        );
    }
}
