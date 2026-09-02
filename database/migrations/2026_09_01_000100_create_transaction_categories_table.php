<?php

use App\Models\Company;
use App\Models\TransactionCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaction_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type'); // income | expense
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['company_id', 'type', 'name']);
        });

        // PRD 3.2: preset default kategori. Backfill companies that predate this table.
        Company::query()->each(fn (Company $company) => TransactionCategory::seedDefaultsFor($company));
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_categories');
    }
};
