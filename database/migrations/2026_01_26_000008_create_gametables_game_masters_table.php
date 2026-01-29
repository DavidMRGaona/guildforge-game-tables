<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_game_masters', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->nullable();

            // External person fields (when not linked to a user)
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();

            // GM-specific fields
            $table->string('role');
            $table->string('custom_title')->nullable();
            $table->boolean('notify_by_email')->default(true);
            $table->boolean('is_name_public')->default(true);
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('role');
            $table->index('user_id');

            // Foreign keys
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_game_masters');
    }
};
