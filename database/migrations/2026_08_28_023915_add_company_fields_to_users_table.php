<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('company_id')->after('id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('owner')->after('password');
            $table->string('status')->default('active')->after('role');
            $table->string('inactive_reason')->nullable()->after('status');
            $table->softDeletes();
        });

        // PRD: email wajib & unik per company — ganti unique global bawaan Breeze.
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique(['company_id', 'email'], 'users_company_id_email_unique');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique('users_company_id_email_unique');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            $table->dropForeign(['company_id']);
            $table->dropColumn(['company_id', 'role', 'status', 'inactive_reason']);
        });
    }
};
