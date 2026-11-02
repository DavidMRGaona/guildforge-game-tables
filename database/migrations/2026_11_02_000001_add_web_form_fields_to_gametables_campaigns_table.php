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
            // All nullable, no default: campaigns created before these were stored lost the answer and show nothing
            $table->integer('min_players')->nullable();
            $table->string('table_format')->nullable();
            $table->string('location', 500)->nullable();
            $table->string('online_url', 500)->nullable();
            $table->text('schedule_notes')->nullable();
            $table->json('genres')->nullable();
            $table->string('tone')->nullable();
            $table->string('character_creation')->nullable();
            $table->json('safety_tools')->nullable();
            $table->json('custom_warnings')->nullable();
        });

        Schema::create('gametables_campaign_content_warnings', function (Blueprint $table): void {
            $table->uuid('campaign_id');
            $table->uuid('content_warning_id');

            $table->primary(['campaign_id', 'content_warning_id']);

            $table->foreign('campaign_id')
                ->references('id')
                ->on('gametables_campaigns')
                ->onDelete('cascade');

            $table->foreign('content_warning_id')
                ->references('id')
                ->on('gametables_content_warnings')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_campaign_content_warnings');

        Schema::table('gametables_campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'min_players',
                'table_format',
                'location',
                'online_url',
                'schedule_notes',
                'genres',
                'tone',
                'character_creation',
                'safety_tools',
                'custom_warnings',
            ]);
        });
    }
};
