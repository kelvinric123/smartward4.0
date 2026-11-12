<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * A ready-made system ward type for Command Center V2's Labour room &
     * nursery group, so the labour room and nursery wards can be given it on
     * their ward's page. Only when no ward type is marked labour room &
     * nursery yet: a hospital that already marked one of its own keeps it.
     */
    public function up(): void
    {
        if (DB::table('ward_types')->where('is_labour_nursery', true)->exists()) {
            return;
        }

        $values = ['name' => 'Labour Room & Nursery', 'is_labour_nursery' => true, 'is_active' => true, 'updated_at' => now()];
        $existing = DB::table('ward_types')->whereNull('hospital_id')->where('code', 'LRN')->first();

        if ($existing) {
            DB::table('ward_types')->where('id', $existing->id)->update($values);

            return;
        }

        DB::table('ward_types')->insert($values + [
            'hospital_id' => null,
            'code' => 'LRN',
            'description' => 'Labour room and nursery wards: their own group in Command Center V2\'s bed occupancy',
            'is_critical_care' => false,
            'is_emergency' => false,
            'sort_order' => (int) DB::table('ward_types')->max('sort_order') + 1,
            'created_at' => now(),
        ]);
    }

    /** Takes the ready-made type out again, unless a ward has been given it since. */
    public function down(): void
    {
        $type = DB::table('ward_types')->whereNull('hospital_id')->where('code', 'LRN')->first();

        if ($type && ! DB::table('wards')->where('ward_type_id', $type->id)->exists()) {
            DB::table('ward_types')->where('id', $type->id)->delete();
        }
    }
};
