<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Wards whose ward type is marked labour room & nursery make up the second
     * of Command Center V2's bed occupancy groups, Labour room & nursery; every
     * other ward counts under General ward & critical. Types already named for
     * a labour room, delivery suite or nursery start out marked; admins change
     * any type from the Ward Types page.
     */
    public function up(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->boolean('is_labour_nursery')->default(false)->after('is_emergency');
        });

        DB::table('ward_types')
            ->where(fn ($query) => $query
                ->where('name', 'like', '%labour%')
                ->orWhere('name', 'like', '%labor%')
                ->orWhere('name', 'like', '%delivery%')
                ->orWhere('name', 'like', '%nursery%'))
            ->update(['is_labour_nursery' => true]);
    }

    public function down(): void
    {
        Schema::table('ward_types', function (Blueprint $table) {
            $table->dropColumn('is_labour_nursery');
        });
    }
};
