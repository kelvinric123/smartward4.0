<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class ApiUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $password = Hash::make('88888888');

        // Define API users
        $users = [
            'api@qmed.asia',
            'api2@qmed.asia',
            'api3@qmed.asia',
            'api4@qmed.asia',
            'api5@qmed.asia',
            'api6@qmed.asia',
        ];

        foreach ($users as $email) {
            User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $email, // Name same as username/email
                    'password' => $password,
                    // 'role' => 'user', // Default is 'user', so we can omit or explicitly set to 'user' if needed. Schema default is 'user'.
                    'email_verified_at' => now(),
                    'remember_token' => Str::random(10),
                ]
            );
        }
    }
}
