<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ward type is scored against several scales, not one, so the single
     * ward_types.clinical_indicator_id becomes a pivot. Anything already
     * pointed at an indicator is carried over before the column goes.
     */
    public function up(): void
    {
        Schema::create('clinical_indicator_ward_type', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_type_id')->constrained('ward_types')->onDelete('cascade');
            $table->foreignId('clinical_indicator_id')->constrained('clinical_indicators')->onDelete('cascade');
            $table->timestamps();

            // Named explicitly: the generated name would be 68 chars, over
            // MySQL's 64-character identifier limit.
            $table->unique(['ward_type_id', 'clinical_indicator_id'], 'ward_type_indicator_unique');
        });

        if (Schema::hasColumn('ward_types', 'clinical_indicator_id')) {
            $existing = DB::table('ward_types')
                ->whereNotNull('clinical_indicator_id')
                ->get(['id', 'clinical_indicator_id']);

            foreach ($existing as $wardType) {
                DB::table('clinical_indicator_ward_type')->insert([
                    'ward_type_id' => $wardType->id,
                    'clinical_indicator_id' => $wardType->clinical_indicator_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            Schema::table('ward_types', function (Blueprint $table) {
                $table->dropConstrainedForeignId('clinical_indicator_id');
            });
        }
    }

    public function down(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->foreignId('clinical_indicator_id')->nullable()->after('name')
                ->constrained('clinical_indicators')->nullOnDelete();
        });

        // Only one can survive the trip back; keep the earliest per ward type.
        $pairs = DB::table('clinical_indicator_ward_type')
            ->orderBy('id')
            ->get(['ward_type_id', 'clinical_indicator_id']);

        $seen = [];
        foreach ($pairs as $pair) {
            if (isset($seen[$pair->ward_type_id])) {
                continue;
            }
            $seen[$pair->ward_type_id] = true;
            DB::table('ward_types')
                ->where('id', $pair->ward_type_id)
                ->update(['clinical_indicator_id' => $pair->clinical_indicator_id]);
        }

        Schema::dropIfExists('clinical_indicator_ward_type');
    }
};
