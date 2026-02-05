<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class SiemUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $userData = [
            'name' => 'SIEM_user_log',
            'email' => 'siem_user_log@hospital.com', // Placeholder email as it's required
            'role' => User::ROLE_SIEM_AUDITOR,
        ];

        // Check if user exists
        $user = User::where('name', $userData['name'])->first();

        if ($user) {
            $user->update([
                'password' => Hash::make('SecurePassW0rdPHKL'),
                'role' => User::ROLE_SIEM_AUDITOR,
            ]);
            $this->command->info('SIEM User updated successfully.');
        } else {
            User::create([
                ...$userData,
                'password' => Hash::make('SecurePassW0rdPHKL'),
            ]);
            $this->command->info('SIEM User created successfully.');
            $this->command->info('SIEM User created successfully.');
        }

        // --- Create MySQL Database User for SIEM ---
        $dbName = \Illuminate\Support\Facades\DB::connection()->getDatabaseName();
        $dbUser = 'SIEM_user_log';
        $dbHost = '%'; // Allow remote access (or restricting to specific subnet if needed)
        $dbPass = 'SecurePassW0rdPHKL';

        try {
            // Create User if not exists
            \Illuminate\Support\Facades\DB::statement("CREATE USER IF NOT EXISTS '{$dbUser}'@'{$dbHost}' IDENTIFIED BY '{$dbPass}';");

            // Alter password to ensure it matches (in case it existed with different pass)
            \Illuminate\Support\Facades\DB::statement("ALTER USER '{$dbUser}'@'{$dbHost}' IDENTIFIED BY '{$dbPass}';");

            // Grant SELECT on specific log tables
            $tables = ['user_activities', 'adt_message_logs', 'ekad_response_logs'];

            foreach ($tables as $table) {
                // Check if table exists to avoid error
                if (\Illuminate\Support\Facades\Schema::hasTable($table)) {
                    \Illuminate\Support\Facades\DB::statement("GRANT SELECT ON `{$dbName}`.`{$table}` TO '{$dbUser}'@'{$dbHost}';");
                } else {
                    $this->command->warn("Table {$table} not found, skipping permission grant.");
                }
            }

            \Illuminate\Support\Facades\DB::statement("FLUSH PRIVILEGES;");

            $this->command->info("MySQL User '{$dbUser}'@'{$dbHost}' configured with read-only access to logs.");

        } catch (\Exception $e) {
            $this->command->error("Failed to configure MySQL user: " . $e->getMessage());
        }
    }
}
