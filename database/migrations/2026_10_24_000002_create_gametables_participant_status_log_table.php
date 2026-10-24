<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per change to a registration's status, for staff. Account names are never copied: the
 * panel reads them live from users, so an anonymized account reads as anonymized here too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_participant_status_log', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_table_id');
            $table->uuid('participant_id')->nullable();
            $table->uuid('participant_user_id')->nullable();
            $table->string('guest_name')->nullable();
            $table->uuid('actor_user_id')->nullable();
            // account, participant (their own registration; a guest has no account) or system
            $table->string('actor_kind');
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->text('reason')->nullable();
            // Microseconds: the changes of one request (a cancellation and its promotion) keep their order
            $table->timestamp('created_at', 6);

            $table->foreign('game_table_id')->references('id')->on('gametables_tables')->cascadeOnDelete();
            // Panel deletes are query deletes: the history stays, unlinked
            $table->foreign('participant_id')->references('id')->on('gametables_participants')->nullOnDelete();
            $table->foreign('participant_user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('actor_user_id')->references('id')->on('users')->nullOnDelete();

            $table->index(['game_table_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_participant_status_log');
    }
};
