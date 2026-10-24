<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gametables_tables', function (Blueprint $table): void {
            // Staff-only switch: when on, the directors manage this table's registrations from the website
            $table->boolean('delegates_registrations')->default(false)->after('auto_confirm');
        });
    }

    public function down(): void
    {
        Schema::table('gametables_tables', function (Blueprint $table): void {
            $table->dropColumn('delegates_registrations');
        });
    }
};
