<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_tables', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_system_id');
            $table->uuid('campaign_id')->nullable();
            $table->uuid('event_id')->nullable();
            $table->uuid('created_by');
            $table->string('title');
            $table->dateTime('starts_at');
            $table->integer('duration_minutes');
            $table->string('table_type');
            $table->string('table_format');
            $table->string('status');
            $table->integer('min_players');
            $table->integer('max_players');
            $table->integer('max_spectators')->default(0);
            $table->text('synopsis')->nullable();
            $table->string('location')->nullable();
            $table->string('online_url')->nullable();
            $table->integer('minimum_age')->nullable();
            $table->string('language', 10)->default('es');
            $table->json('genres')->nullable();
            $table->string('tone')->nullable();
            $table->string('experience_level')->nullable();
            $table->string('character_creation')->nullable();
            $table->json('safety_tools')->nullable();
            $table->json('custom_warnings')->nullable();
            $table->string('registration_type')->default('everyone');
            $table->integer('members_early_access_days')->default(0);
            $table->dateTime('registration_opens_at')->nullable();
            $table->dateTime('registration_closes_at')->nullable();
            $table->boolean('auto_confirm')->default(true);
            $table->boolean('is_published')->default(false);
            $table->dateTime('published_at')->nullable();
            $table->text('notes')->nullable();
            $table->string('notification_email')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('starts_at');
            $table->index('is_published');
            $table->index('created_by');
            $table->index(['is_published', 'starts_at']);

            // Foreign keys
            $table->foreign('game_system_id')
                ->references('id')
                ->on('gametables_game_systems')
                ->onDelete('cascade');

            $table->foreign('campaign_id')
                ->references('id')
                ->on('gametables_campaigns')
                ->onDelete('set null');

            $table->foreign('created_by')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_tables');
    }
};
