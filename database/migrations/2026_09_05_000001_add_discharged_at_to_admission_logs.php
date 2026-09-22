<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * The discharge time of an admission episode, stamped on the 'discharge'
     * log row.
     *
     * patients.discharged_at only ever holds the LAST discharge, so a patient
     * with several admissions loses the earlier ones - and the manual discharge
     * flow lets staff back-date the discharge, which was previously not stored
     * anywhere. The discharge summary needs the real time per episode.
     */
    public function up(): void
    {
        Schema::table('admission_logs', function (Blueprint $table) {
            $table->timestamp('discharged_at')->nullable()->after('admitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('admission_logs', function (Blueprint $table) {
            $table->dropColumn('discharged_at');
        });
    }
};
