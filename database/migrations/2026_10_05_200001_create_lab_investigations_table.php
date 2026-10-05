<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One investigation (test or panel) of a lab order placed in the HIS
        Schema::create('lab_investigations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            // 'his' = received from the HIS feed, 'sample' = demo data (Settings > Patient Additional Info)
            $table->string('source', 16)->default('his');
            $table->string('order_no', 64);
            $table->string('test_code', 32)->nullable();
            $table->string('test_name');
            $table->string('category', 32)->default('other');
            $table->string('specimen')->nullable();
            $table->string('priority', 16)->default('routine');
            $table->string('ordered_by')->nullable();
            $table->dateTime('ordered_at');
            $table->string('status', 16)->default('ordered');
            $table->dateTime('collected_at')->nullable();
            $table->dateTime('resulted_at')->nullable();
            $table->json('results')->nullable();
            $table->text('comment')->nullable();
            // When the result should be reviewed; computed from the priority when the HIS does not send it
            $table->dateTime('review_due_at')->nullable();
            $table->dateTime('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reviewed_by_name')->nullable();
            $table->timestamps();

            $table->unique(['source', 'order_no', 'test_name']);
            $table->index(['patient_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_investigations');
    }
};
