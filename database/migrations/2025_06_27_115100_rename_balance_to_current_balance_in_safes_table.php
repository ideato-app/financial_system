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
        Schema::table('safes', function (Blueprint $table) {
            $table->renameColumn('balance', 'current_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('safes', function (Blueprint $table) {
            $table->renameColumn('current_balance', 'balance');
        });
    }
};
