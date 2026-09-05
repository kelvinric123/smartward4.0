<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Infusion events (pump alarms, near-empty, completion, battery) join the
     * ward notification queue alongside EWS and patient smart calls.
     *
     * A patient can run several pumps at once, so (patient, type, category) is
     * no longer unique enough to dedupe on - `reference` carries the event's
     * origin (pump device + medication) so two pumps alarming on the same
     * patient stay two notifications instead of overwriting each other.
     *
     * `meta` holds the structured detail the nurse needs at a glance (drug,
     * rate, volume/time remaining, alarm text) without stuffing it all into
     * the message sentence.
     */
    public function up(): void
    {
        Schema::table('ward_notifications', function (Blueprint $table) {
            $table->string('reference')->nullable()->after('category');
            $table->json('meta')->nullable()->after('ews_score');

            $table->index(['ward_id', 'type', 'status']);
            $table->index(['reference', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('ward_notifications', function (Blueprint $table) {
            $table->dropIndex(['ward_id', 'type', 'status']);
            $table->dropIndex(['reference', 'status']);
            $table->dropColumn(['reference', 'meta']);
        });
    }
};
