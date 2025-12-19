<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds extended fields for B.Braun MDC (Medical Device Communication) data
     */
    public function up(): void
    {
        // Add extended fields to bbraun_hl7_logs table
        Schema::table('bbraun_hl7_logs', function (Blueprint $table) {
            // Location fields
            if (!Schema::hasColumn('bbraun_hl7_logs', 'ward')) {
                $table->string('ward')->nullable()->after('patient_name');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'room')) {
                $table->string('room')->nullable()->after('ward');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'bed')) {
                $table->string('bed')->nullable()->after('room');
            }
            
            // Extended device fields
            if (!Schema::hasColumn('bbraun_hl7_logs', 'device_uuid')) {
                $table->string('device_uuid')->nullable()->after('device_id');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'pump_model')) {
                $table->string('pump_model')->nullable()->after('device_uuid');
            }
            
            // Extended infusion data
            if (!Schema::hasColumn('bbraun_hl7_logs', 'remaining_minutes')) {
                $table->integer('remaining_minutes')->nullable()->after('remaining_volume');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'drug_concentration')) {
                $table->decimal('drug_concentration', 10, 4)->nullable()->after('medication_name');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'dose_rate')) {
                $table->decimal('dose_rate', 10, 4)->nullable()->after('remaining_minutes');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'dose_unit')) {
                $table->string('dose_unit')->nullable()->after('dose_rate');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'syringe_size')) {
                $table->decimal('syringe_size', 10, 2)->nullable()->after('dose_unit');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'delivery_mode')) {
                $table->string('delivery_mode')->nullable()->after('syringe_size');
            }
            
            // Extended alarm fields
            if (!Schema::hasColumn('bbraun_hl7_logs', 'alarm_priority')) {
                $table->string('alarm_priority')->nullable()->after('alarm_message');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'alarm_state')) {
                $table->string('alarm_state')->nullable()->after('alarm_priority');
            }
            
            // Power and battery fields
            if (!Schema::hasColumn('bbraun_hl7_logs', 'power_status')) {
                $table->string('power_status')->nullable()->after('alarm_state');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'battery_percent')) {
                $table->integer('battery_percent')->nullable()->after('power_status');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'battery_minutes_remaining')) {
                $table->integer('battery_minutes_remaining')->nullable()->after('battery_percent');
            }
            
            // Network fields
            if (!Schema::hasColumn('bbraun_hl7_logs', 'wifi_strength')) {
                $table->integer('wifi_strength')->nullable()->after('battery_minutes_remaining');
            }
            if (!Schema::hasColumn('bbraun_hl7_logs', 'device_ip')) {
                $table->string('device_ip', 45)->nullable()->after('wifi_strength');
            }
        });

        // Add extended fields to infusion_pumps table
        Schema::table('infusion_pumps', function (Blueprint $table) {
            // patient_id and linked_at are handled by 2025_12_19_000002 migration
            if (!Schema::hasColumn('infusion_pumps', 'device_uuid')) {
                $table->string('device_uuid')->nullable()->after('device_type');
            }
            if (!Schema::hasColumn('infusion_pumps', 'pump_model')) {
                $table->string('pump_model')->nullable()->after('device_uuid');
            }
            if (!Schema::hasColumn('infusion_pumps', 'firmware_version')) {
                $table->string('firmware_version')->nullable()->after('pump_model');
            }
            if (!Schema::hasColumn('infusion_pumps', 'power_status')) {
                $table->string('power_status')->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('infusion_pumps', 'battery_percent')) {
                $table->integer('battery_percent')->nullable()->after('power_status');
            }
            if (!Schema::hasColumn('infusion_pumps', 'battery_minutes_remaining')) {
                $table->integer('battery_minutes_remaining')->nullable()->after('battery_percent');
            }
            if (!Schema::hasColumn('infusion_pumps', 'wifi_strength')) {
                $table->integer('wifi_strength')->nullable()->after('battery_minutes_remaining');
            }
            if (!Schema::hasColumn('infusion_pumps', 'device_ip')) {
                $table->string('device_ip', 45)->nullable()->after('wifi_strength');
            }
        });

        // Add extended fields to infusions table
        Schema::table('infusions', function (Blueprint $table) {
            if (!Schema::hasColumn('infusions', 'drug_concentration')) {
                $table->decimal('drug_concentration', 10, 4)->nullable()->after('medication_code');
            }
            if (!Schema::hasColumn('infusions', 'drug_concentration_unit')) {
                $table->string('drug_concentration_unit')->nullable()->after('drug_concentration');
            }
            if (!Schema::hasColumn('infusions', 'care_area')) {
                $table->string('care_area')->nullable()->after('drug_concentration_unit');
            }
            if (!Schema::hasColumn('infusions', 'delivery_mode')) {
                $table->string('delivery_mode')->nullable()->after('status');
            }
            if (!Schema::hasColumn('infusions', 'syringe_size')) {
                $table->decimal('syringe_size', 10, 2)->nullable()->after('delivery_mode');
            }
            if (!Schema::hasColumn('infusions', 'syringe_manufacturer')) {
                $table->string('syringe_manufacturer')->nullable()->after('syringe_size');
            }
            if (!Schema::hasColumn('infusions', 'alarm_priority')) {
                $table->string('alarm_priority')->nullable()->after('alarm_message');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bbraun_hl7_logs', function (Blueprint $table) {
            $columns = [
                'ward', 'room', 'bed', 'device_uuid', 'pump_model',
                'remaining_minutes', 'drug_concentration', 'dose_rate', 'dose_unit',
                'syringe_size', 'delivery_mode', 'alarm_priority', 'alarm_state',
                'power_status', 'battery_percent', 'battery_minutes_remaining',
                'wifi_strength', 'device_ip'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('bbraun_hl7_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('infusion_pumps', function (Blueprint $table) {
            // patient_id and linked_at are handled by 2025_12_19_000002 migration
            $columns = [
                'device_uuid', 'pump_model', 'firmware_version',
                'power_status', 'battery_percent', 'battery_minutes_remaining',
                'wifi_strength', 'device_ip'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('infusion_pumps', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('infusions', function (Blueprint $table) {
            $columns = [
                'drug_concentration', 'drug_concentration_unit', 'care_area',
                'delivery_mode', 'syringe_size', 'syringe_manufacturer', 'alarm_priority'
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('infusions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
