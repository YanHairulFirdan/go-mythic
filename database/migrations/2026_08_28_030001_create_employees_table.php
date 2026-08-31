<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            // ponytail: (company_id, user_id) unique tak mencegah duplikat NULL user_id (worker); diperketat saat US-AUTH-07.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->boolean('has_access_to_system')->default(false);
            $table->string('status')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'user_id'], 'employees_company_id_user_id_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
