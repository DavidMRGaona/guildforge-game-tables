<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The web form took 500 characters for the location and the online link, over the 255 these
 * columns held, and a minimum age of 0, under the 1 the panel takes. Both now take what the web,
 * the panel and campaigns take; a 0 meant no requirement and becomes empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gametables_tables', function (Blueprint $table): void {
            $table->string('location', 500)->nullable()->change();
            $table->string('online_url', 500)->nullable()->change();
        });

        DB::table('gametables_tables')->where('minimum_age', 0)->update(['minimum_age' => null]);
    }

    public function down(): void
    {
        // Lengths are kept: going back to 255 would cut what was saved since
    }
};
