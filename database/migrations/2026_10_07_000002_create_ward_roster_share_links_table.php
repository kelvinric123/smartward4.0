<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // View-only links to a ward's AI Nurse Schedule for staff without a SmartWard
        // sign-in. The token in the link is the access, so only its sha256 is looked
        // up; a link stops working when it expires or a manager turns it off.
        Schema::create('ward_roster_share_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            // The token itself, encrypted with the app key, so the link can be copied again
            $table->text('token')->nullable();
            $table->string('label', 100)->nullable();

            // What the link shows: some of roster, nurse, assign, acuity, workload
            $table->json('sections');
            // rolling: always the current week (and next week); fixed: the week in week_start
            $table->string('week_mode', 8)->default('rolling');
            $table->date('week_start')->nullable();
            // Patients as initials or the bed only; a link never shows full names
            $table->string('names', 16)->default('hidden');

            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('created_by_name')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();

            $table->index(['ward_id', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ward_roster_share_links');
    }
};
