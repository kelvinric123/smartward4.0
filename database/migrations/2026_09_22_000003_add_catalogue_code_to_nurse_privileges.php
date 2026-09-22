<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Clinical privileges become a built-in checklist (App\Support\
     * NursePrivilegeCatalogue): code links a privilege to its entry there,
     * and stays empty for a privilege of the hospital's own. The "specific"
     * level is now called "advanced".
     */
    public function up(): void
    {
        Schema::table('nurse_privileges', function (Blueprint $table) {
            $table->string('code', 60)->nullable()->after('nurse_id');

            // One row per list entry, as for names
            $table->unique(['nurse_id', 'code']);
        });

        DB::table('nurse_privileges')->where('category', 'specific')->update(['category' => 'advanced']);
    }

    public function down(): void
    {
        DB::table('nurse_privileges')->where('category', 'advanced')->update(['category' => 'specific']);

        Schema::table('nurse_privileges', function (Blueprint $table) {
            $table->dropUnique(['nurse_id', 'code']);
            $table->dropColumn('code');
        });
    }
};
