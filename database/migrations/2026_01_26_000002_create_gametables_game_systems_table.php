<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_game_systems', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->uuid('publisher_id')->nullable();
            $table->string('edition')->nullable();
            $table->integer('year')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('website_url')->nullable();
            $table->string('game_master_title')->default('Director de juego');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('is_active');
            $table->index('name');

            // Foreign keys
            $table->foreign('publisher_id')
                ->references('id')
                ->on('gametables_publishers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_game_systems');
    }
};
