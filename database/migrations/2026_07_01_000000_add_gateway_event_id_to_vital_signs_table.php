<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add a gateway-supplied idempotency key so retried submissions (e.g. after
     * a lost ACK over flaky Wi-Fi) do not create duplicate vital-sign records.
     *
     * Nullable + unique: existing rows keep NULL (multiple NULLs are allowed in a
     * MySQL unique index), while any two rows carrying the same gateway_event_id
     * are rejected at the database level.
     */
    public function up(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->string('gateway_event_id', 64)->nullable()->after('admission_id');
            $table->unique('gateway_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->dropUnique(['gateway_event_id']);
            $table->dropColumn('gateway_event_id');
        });
    }
};
