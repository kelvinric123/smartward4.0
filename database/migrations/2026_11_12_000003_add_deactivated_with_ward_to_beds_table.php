<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ward's beds follow it (App\Models\Bed::followWard): deactivating a ward
     * takes its beds out of use, marked deactivated_with_ward, and reactivating
     * it brings those beds back but not ones deactivated on their own.
     *
     * Beds already in a deactivated ward (or one that is gone) follow it now.
     * Safe to run again: the column is only added once.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('beds', 'deactivated_with_ward')) {
            Schema::table('beds', function (Blueprint $table) {
                $table->boolean('deactivated_with_ward')->default(false)->after('is_active');
            });
        }

        DB::table('beds')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereIn('ward_id', DB::table('wards')->select('id')->where('is_active', false))
                    ->orWhereNotIn('ward_id', DB::table('wards')->select('id'));
            })
            ->update(['is_active' => false, 'deactivated_with_ward' => true, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('beds')->where('deactivated_with_ward', true)->update(['is_active' => true]);

        Schema::table('beds', function (Blueprint $table) {
            $table->dropColumn('deactivated_with_ward');
        });
    }
};
