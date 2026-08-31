<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// ponytail: pakai change() anonymous — SQLite + doctrine/dbal tidak diperlukan di Laravel 11+.

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->nullable()->after('email');
            $table->string('email')->nullable()->change();
            $table->unique(['company_id', 'username'], 'users_company_id_username_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_company_id_username_unique');
            $table->dropColumn('username');
        });
    }
};
