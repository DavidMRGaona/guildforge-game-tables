<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_participants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('game_table_id');
            $table->uuid('user_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('cancellation_token', 64)->nullable()->unique();
            $table->string('role');
            $table->string('status');
            $table->integer('waiting_list_position')->nullable();
            $table->text('notes')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->timestamps();

            // Indexes
            $table->index('status');
            $table->index('role');
            $table->index('user_id');

            // Foreign keys
            $table->foreign('game_table_id')
                ->references('id')
                ->on('gametables_tables')
                ->onDelete('cascade');

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_participants');
    }
};
