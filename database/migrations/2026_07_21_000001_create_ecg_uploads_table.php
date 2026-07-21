<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per ECG file received from an ECG gateway. The UNIQUE event id
     * makes gateway retries idempotent (a replayed upload is acknowledged, not
     * duplicated), and the row records where the XML/PDF landed on disk so the
     * receipt can be audited even though the ECG viewers read the folder.
     */
    public function up(): void
    {
        Schema::create('ecg_uploads', function (Blueprint $table) {
            $table->id();
            $table->string('gateway_event_id', 64)->unique();
            $table->string('gateway_id')->nullable()->index();
            $table->string('patient_code')->nullable()->index();  // PatientID from the ECG XML (MRN/RN)
            $table->string('xml_filename')->nullable();
            $table->string('pdf_filename')->nullable();
            $table->unsignedBigInteger('file_size')->default(0);
            $table->timestamp('captured_at')->nullable();          // when the ECG machine sent it to the gateway
            $table->string('source_ip', 45)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecg_uploads');
    }
};
