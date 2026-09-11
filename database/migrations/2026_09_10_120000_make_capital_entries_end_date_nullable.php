<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An open-ended capital entry (no fixed end) stores end_date = NULL. Running
     * totals then treat "today" as the effective end; annual reports use their
     * own calendar-year window.
     */
    public function up(): void
    {
        Schema::table('capital_entries', function (Blueprint $table) {
            $table->date('end_date')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('capital_entries', function (Blueprint $table) {
            $table->date('end_date')->nullable(false)->change();
        });
    }
};
