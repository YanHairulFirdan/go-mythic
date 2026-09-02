<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            // Preset key ("blue") or a "#rrggbb" hex string; null falls back to the app default.
            $table->string('primary_color')->nullable()->after('address');
            // Path on the "public" disk, e.g. "company-logos/xxxx.png"; null means no custom logo.
            $table->string('logo_path')->nullable()->after('primary_color');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['primary_color', 'logo_path']);
        });
    }
};
