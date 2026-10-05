<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links wards and beds to C+ (Cerebral HIS) Bed Management, so the
 * rpa_cplus_smartward sync finds them again by C+ id however they are
 * renamed here. A ward is a C+ location of one facility; a bed is a C+ bed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wards', function (Blueprint $table) {
            $table->string('cplus_facility_id', 16)->nullable()->after('is_active');
            $table->string('cplus_location_id', 32)->nullable()->after('cplus_facility_id');
            $table->timestamp('cplus_synced_at')->nullable()->after('cplus_location_id');
            $table->index(['cplus_facility_id', 'cplus_location_id']);
        });

        Schema::table('beds', function (Blueprint $table) {
            $table->string('cplus_bed_id', 32)->nullable()->after('is_active');
            $table->string('cplus_room_no', 32)->nullable()->after('cplus_bed_id');
            // C+'s own reading of the bed: occupied, available, out_of_service or unknown.
            $table->string('cplus_status', 32)->nullable()->after('cplus_room_no');
            $table->timestamp('cplus_synced_at')->nullable()->after('cplus_status');
            $table->index('cplus_bed_id');
        });
    }

    public function down(): void
    {
        Schema::table('beds', function (Blueprint $table) {
            $table->dropIndex(['cplus_bed_id']);
            $table->dropColumn(['cplus_bed_id', 'cplus_room_no', 'cplus_status', 'cplus_synced_at']);
        });

        Schema::table('wards', function (Blueprint $table) {
            $table->dropIndex(['cplus_facility_id', 'cplus_location_id']);
            $table->dropColumn(['cplus_facility_id', 'cplus_location_id', 'cplus_synced_at']);
        });
    }
};
