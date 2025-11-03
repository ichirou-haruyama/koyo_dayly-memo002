<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class RolesAndUsersSeeder extends Seeder
{
    public function run(): void
    {
        $roles = collect(['admin', 'manager', 'user'])
            ->mapWithKeys(function (string $name) {
                $role = Role::firstOrCreate(['name' => $name]);
                return [$name => $role->id];
            });

        User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role_id' => $roles->get('admin'),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'manager@example.com'],
            [
                'name' => 'Manager',
                'password' => Hash::make('password'),
                'role_id' => $roles->get('manager'),
            ]
        );

        User::query()->firstOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'User',
                'password' => Hash::make('password'),
                'role_id' => $roles->get('user'),
            ]
        );
    }
}
