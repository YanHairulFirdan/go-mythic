<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // History of top-ups against an active capital_entries row (US-MK-01B AC5,
        // consumed by US-MK-03). No soft deletes per DBML.
        Schema::create('capital_topups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('capital_entry_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 14, 2);
            $table->foreignId('changed_by')->constrained('users');
            $table->timestamp('changed_at');
            $table->date('extended_end_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('capital_topups');
    }
};
