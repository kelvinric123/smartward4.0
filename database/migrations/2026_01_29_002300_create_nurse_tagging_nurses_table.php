<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('nurse_tagging_nurses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nurse_id')->constrained('nurses')->cascadeOnDelete();
            $table->foreignId('tagging_nurse_id')->constrained('nurses')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['nurse_id', 'tagging_nurse_id']);
        });

        // Migrate existing data from tagged_nurse_id to the new pivot table
        $nurses = DB::table('nurses')->whereNotNull('tagged_nurse_id')->get();
        foreach ($nurses as $nurse) {
            DB::table('nurse_tagging_nurses')->insert([
                'nurse_id' => $nurse->id,
                'tagging_nurse_id' => $nurse->tagged_nurse_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Drop the old tagged_nurse_id column
        Schema::table('nurses', function (Blueprint $table) {
            $table->dropForeign(['tagged_nurse_id']);
            $table->dropColumn('tagged_nurse_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Re-add the tagged_nurse_id column
        Schema::table('nurses', function (Blueprint $table) {
            $table->foreignId('tagged_nurse_id')->nullable()->after('ward_id')->constrained('nurses')->nullOnDelete();
        });

        // Migrate data back (only first tagging nurse per nurse)
        $taggings = DB::table('nurse_tagging_nurses')
            ->select('nurse_id', DB::raw('MIN(tagging_nurse_id) as tagging_nurse_id'))
            ->groupBy('nurse_id')
            ->get();

        foreach ($taggings as $tagging) {
            DB::table('nurses')
                ->where('id', $tagging->nurse_id)
                ->update(['tagged_nurse_id' => $tagging->tagging_nurse_id]);
        }

        Schema::dropIfExists('nurse_tagging_nurses');
    }
};
