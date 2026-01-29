<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_campaign_gm', function (Blueprint $table): void {
            $table->uuid('campaign_id');
            $table->uuid('game_master_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['campaign_id', 'game_master_id']);

            $table->foreign('campaign_id')
                ->references('id')
                ->on('gametables_campaigns')
                ->onDelete('cascade');

            $table->foreign('game_master_id')
                ->references('id')
                ->on('gametables_game_masters')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_campaign_gm');
    }
};
