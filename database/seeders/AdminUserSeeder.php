<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@idenna.gob.ve'],
            [
                'name' => 'Administrador Nacional',
                'password' => Hash::make('Admin123!'),
                'organization' => 'IDENNA',
                'is_active' => true,
            ]
        );

        $admin->assignRole('super-admin');
    }
}
