<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_tables_event_configs', function (Blueprint $table): void {
            // event_id is the primary key (one config per event)
            $table->uuid('event_id')->primary();
            $table->boolean('tables_enabled')->default(false);
            $table->string('scheduling_mode')->default('free');
            $table->json('time_slots')->nullable();
            $table->string('location_mode')->default('free');
            $table->string('fixed_location')->nullable();
            $table->json('eligibility_override')->nullable();
            $table->boolean('early_access_enabled')->default(false);
            $table->timestamp('creation_opens_at')->nullable();
            $table->json('early_access_tier')->nullable();
            $table->timestamps();

            // Foreign key to events table
            $table->foreign('event_id')
                ->references('id')
                ->on('events')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_tables_event_configs');
    }
};
