<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_campaigns', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_system_id');
            $table->uuid('created_by');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency')->nullable(); // weekly, biweekly, monthly, irregular
            $table->string('status'); // recruiting, active, on_hold, completed, cancelled
            $table->integer('session_count')->nullable();
            $table->integer('current_session')->default(0);
            $table->boolean('accepts_new_players')->default(true);
            $table->integer('max_players')->nullable();
            $table->boolean('is_published')->default(false);
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('created_by');
            $table->index('is_published');

            // Foreign keys
            $table->foreign('game_system_id')
                ->references('id')
                ->on('gametables_game_systems')
                ->onDelete('cascade');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // Campaign players pivot table
        Schema::create('gametables_campaign_players', function (Blueprint $table): void {
            $table->uuid('campaign_id');
            $table->uuid('user_id');
            $table->timestamps();

            $table->primary(['campaign_id', 'user_id']);

            $table->foreign('campaign_id')
                ->references('id')
                ->on('gametables_campaigns')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_campaign_players');
        Schema::dropIfExists('gametables_campaigns');
    }
};
