<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gametables_participants', function (Blueprint $table): void {
            // The registration was made by the campaign roster (it goes first on the waiting list)
            $table->boolean('from_roster')->default(false);
            // Why a roster registration was cancelled: left the campaign or missed this session
            $table->string('cancellation_reason', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gametables_participants', function (Blueprint $table): void {
            $table->dropColumn(['from_roster', 'cancellation_reason']);
        });
    }
};
