<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How often each scale should be reassessed. Off by default: the interval
     * is local policy rather than part of the instrument, so it lives on the
     * row, where the library sync leaves it alone.
     *
     * Both levels count from the patient's last score for the scale, or from
     * admission before the first. Past the suggested interval the patient shows
     * as due on the ward dashboard, past the warning level as overdue.
     *
     * Switching monitoring off keeps the minutes, so switching it back on
     * restores the levels last used.
     */
    public function up(): void
    {
        Schema::table('clinical_indicators', function (Blueprint $table) {
            $table->boolean('monitoring_enabled')->default(false)->after('is_active');
            $table->unsignedInteger('monitoring_suggested_minutes')->nullable()->after('monitoring_enabled');
            $table->unsignedInteger('monitoring_warning_minutes')->nullable()->after('monitoring_suggested_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('clinical_indicators', function (Blueprint $table) {
            $table->dropColumn(['monitoring_enabled', 'monitoring_suggested_minutes', 'monitoring_warning_minutes']);
        });
    }
};
