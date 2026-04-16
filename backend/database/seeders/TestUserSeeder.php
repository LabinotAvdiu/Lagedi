<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'test@test.com'],
            [
                'first_name'        => 'Test',
                'last_name'         => 'User',
                'phone'             => '+33600000000',
                'role'              => UserRole::User,
                'password'          => '123456789', // hashed automatically by the 'hashed' cast
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Test user created/updated: test@test.com / 123456789');
    }
}
