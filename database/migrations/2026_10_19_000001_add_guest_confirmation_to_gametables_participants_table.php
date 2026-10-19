<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gametables_participants', function (Blueprint $table): void {
            $table->dateTime('email_confirmed_at')->nullable();
            $table->dateTime('confirmation_expires_at')->nullable();
            $table->string('confirmation_token', 64)->nullable()->unique();
            $table->dateTime('confirmation_sent_at')->nullable();
            $table->string('confirmation_mode', 8)->nullable();
            $table->string('fingerprint', 64)->nullable();
        });

        // Every existing registration was made before confirmation existed: it counts as confirmed
        DB::table('gametables_participants')->update([
            'email_confirmed_at' => DB::raw('COALESCE(created_at, CURRENT_TIMESTAMP)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('gametables_participants', function (Blueprint $table): void {
            $table->dropUnique(['confirmation_token']);
            $table->dropColumn([
                'email_confirmed_at',
                'confirmation_expires_at',
                'confirmation_token',
                'confirmation_sent_at',
                'confirmation_mode',
                'fingerprint',
            ]);
        });
    }
};
