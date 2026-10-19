<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Backstop under the table lock: one active registration per guest email and table.
        // Partial and expression indexes are not in the schema builder; both drivers take this SQL.
        DB::statement(
            'CREATE UNIQUE INDEX gametables_participants_active_guest_email_unique '
            .'ON gametables_participants (game_table_id, lower(email)) '
            ."WHERE user_id IS NULL AND email IS NOT NULL AND status IN ('pending', 'confirmed', 'waiting_list')"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS gametables_participants_active_guest_email_unique');
    }
};
