<?php

namespace Database\Seeders;

use App\Models\ApiUser;
use Illuminate\Database\Seeder;

class ApiUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define API users (username format)
        $users = [
            'api@qmed.asia',
            'api2@qmed.asia',
            'api3@qmed.asia',
            'api4@qmed.asia',
            'api5@qmed.asia',
            'api6@qmed.asia',
        ];

        foreach ($users as $username) {
            ApiUser::firstOrCreate(
                ['username' => $username],
                [
                    'name' => $username,
                    'password' => '88888888', // ApiUser model auto-hashes via setPasswordAttribute
                    'is_active' => true,
                    'description' => 'API user for external integrations',
                ]
            );
        }
    }
}
