<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop existing non-unique index if exists
        try {
            DB::statement('DROP INDEX medicines_name_index ON medicines');
        } catch (\Exception $e) {
            // Index doesn't exist, continue
        }

        // Add unique constraint
        Schema::table('medicines', function (Blueprint $table) {
            $table->unique('name', 'medicines_name_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('medicines', function (Blueprint $table) {
            $table->dropUnique('medicines_name_unique');
        });
    }
};
