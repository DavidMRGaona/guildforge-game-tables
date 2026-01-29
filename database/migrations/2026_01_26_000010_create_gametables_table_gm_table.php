<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_table_gm', function (Blueprint $table): void {
            $table->uuid('game_table_id');
            $table->uuid('game_master_id');
            $table->string('source'); // 'inherited' or 'local'
            $table->boolean('excluded')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->primary(['game_table_id', 'game_master_id']);

            $table->foreign('game_table_id')
                ->references('id')
                ->on('gametables_tables')
                ->onDelete('cascade');

            $table->foreign('game_master_id')
                ->references('id')
                ->on('gametables_game_masters')
                ->onDelete('cascade');

            $table->index('source');
            $table->index('excluded');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_table_gm');
    }
};
