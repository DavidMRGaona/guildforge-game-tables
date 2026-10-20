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
            // Who may join the roster: 'anyone' (any account) or 'members_only'
            $table->string('roster_access', 20)->default('anyone');
        });
    }

    public function down(): void
    {
        Schema::table('gametables_campaigns', function (Blueprint $table): void {
            $table->dropColumn('roster_access');
        });
    }
};
