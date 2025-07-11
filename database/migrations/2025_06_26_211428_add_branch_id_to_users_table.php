<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('branch_id')->nullable()->constrained()->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            try {
                \Illuminate\Support\Facades\DB::statement('ALTER TABLE `users` DROP FOREIGN KEY `users_branch_id_foreign`');
            } catch (\Throwable $e) {
                // Ignore if already dropped or doesn't exist
            }
            if (Schema::hasColumn('users', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
        });
    }
};
