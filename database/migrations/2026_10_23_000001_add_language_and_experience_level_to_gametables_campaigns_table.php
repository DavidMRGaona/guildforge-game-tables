<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gametables_campaigns', function (Blueprint $table): void {
            // No default: campaigns created before these were stored lost the answer and show nothing
            $table->string('language', 10)->nullable();
            $table->string('experience_level')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gametables_campaigns', function (Blueprint $table): void {
            $table->dropColumn(['language', 'experience_level']);
        });
    }
};
