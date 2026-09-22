<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Credentialing and Privileging tab of the nurse edit page.
     *
     * - nurse_credentials: the licences and certificates on a nurse's file
     *   (Annual Practising Certificate, registration, post-basic, BLS/ACLS...)
     *   with the date each one runs out and who sighted the original. A
     *   renewal is a new row, so the old ones stay as the credential's history.
     * - nurse_privileges: the clinical procedures a nurse is allowed to do
     *   (IV cannulation, blood transfusion...), one row per privilege, whose
     *   status moves between granted, supervised, suspended and withdrawn.
     */
    public function up(): void
    {
        Schema::create('nurse_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained('nurses')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('title', 150);
            $table->string('reference_number', 100)->nullable();
            $table->string('issuing_body', 150)->nullable();
            $table->date('issued_on')->nullable();
            $table->date('expires_on')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['nurse_id', 'type']);
        });

        Schema::create('nurse_privileges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained('nurses')->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('category', 20);
            $table->string('status', 20);
            $table->timestamp('status_changed_at')->nullable();
            $table->date('granted_on')->nullable();
            $table->date('review_on')->nullable();
            $table->string('approved_by', 150)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One row per privilege: re-granting one edits its status
            $table->unique(['nurse_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nurse_privileges');
        Schema::dropIfExists('nurse_credentials');
    }
};
