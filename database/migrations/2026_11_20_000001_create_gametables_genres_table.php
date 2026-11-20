<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog of genres. Tables and campaigns keep storing the keys of their genres in their
 * `genres` JSON column; the genres themselves are created by GenresSeeder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_genres', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 50)->unique();
            $table->string('name', 50);
            $table->string('color', 20)->default('gray');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_genres');
    }
};
