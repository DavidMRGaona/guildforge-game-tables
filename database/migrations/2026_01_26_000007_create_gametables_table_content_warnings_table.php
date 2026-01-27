<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_table_content_warnings', function (Blueprint $table): void {
            $table->uuid('game_table_id');
            $table->uuid('content_warning_id');

            $table->primary(['game_table_id', 'content_warning_id']);

            $table->foreign('game_table_id')
                ->references('id')
                ->on('gametables_tables')
                ->onDelete('cascade');

            $table->foreign('content_warning_id')
                ->references('id')
                ->on('gametables_content_warnings')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_table_content_warnings');
    }
};
