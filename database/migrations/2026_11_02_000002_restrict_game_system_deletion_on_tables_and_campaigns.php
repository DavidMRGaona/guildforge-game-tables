<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleting a game system took its tables and campaigns with it (cascade). The model already refuses
 * to delete a system in use; the database now refuses too, should anything skip the model.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gametables_tables', function (Blueprint $table): void {
            $table->dropForeign(['game_system_id']);
            $table->foreign('game_system_id')->references('id')->on('gametables_game_systems')->restrictOnDelete();
        });

        Schema::table('gametables_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['game_system_id']);
            $table->foreign('game_system_id')->references('id')->on('gametables_game_systems')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gametables_tables', function (Blueprint $table): void {
            $table->dropForeign(['game_system_id']);
            $table->foreign('game_system_id')->references('id')->on('gametables_game_systems')->onDelete('cascade');
        });

        Schema::table('gametables_campaigns', function (Blueprint $table): void {
            $table->dropForeign(['game_system_id']);
            $table->foreign('game_system_id')->references('id')->on('gametables_game_systems')->onDelete('cascade');
        });
    }
};
