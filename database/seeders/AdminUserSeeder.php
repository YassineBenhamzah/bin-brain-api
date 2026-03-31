<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@bin.local'],
            [
                'name' => 'Root Admin',
                'password' => Hash::make('brain123'),
                'role' => 'admin',
            ]
        );
    }
}
