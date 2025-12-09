<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the status enum to add 'unmapped' status
        // For SQLite, we need to recreate the column
        if (DB::getDriverName() === 'sqlite') {
            // SQLite doesn't support ALTER COLUMN for enum, so we'll use a workaround
            // The status column will accept the new value as SQLite doesn't enforce enums
        } else {
            // For MySQL/PostgreSQL
            DB::statement("ALTER TABLE adt_message_logs MODIFY COLUMN status ENUM('received', 'processed', 'failed', 'ignored', 'unmapped') DEFAULT 'received'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("ALTER TABLE adt_message_logs MODIFY COLUMN status ENUM('received', 'processed', 'failed', 'ignored') DEFAULT 'received'");
        }
    }
};

