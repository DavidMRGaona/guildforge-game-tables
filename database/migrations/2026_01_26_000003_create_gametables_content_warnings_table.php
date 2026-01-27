<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_content_warnings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('label');
            $table->text('description')->nullable();
            $table->string('severity'); // mild, moderate, severe
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Indexes
            $table->index('is_active');
            $table->index('severity');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_content_warnings');
    }
};
