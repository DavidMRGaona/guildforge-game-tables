<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_campaign_join_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->uuid('user_id');
            $table->string('status', 20);
            $table->text('message')->nullable();
            $table->text('decision_reason')->nullable();
            $table->uuid('decided_by')->nullable();
            $table->dateTime('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('gametables_campaigns')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('decided_by')->references('id')->on('users')->nullOnDelete();

            $table->index(['campaign_id', 'status']);
        });

        // Backstop under the campaign lock: one pending request per person and campaign.
        // Partial indexes are not in the schema builder; both drivers take this SQL.
        DB::statement(
            'CREATE UNIQUE INDEX gametables_join_requests_one_pending '
            ."ON gametables_campaign_join_requests (campaign_id, user_id) WHERE status = 'pending'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS gametables_join_requests_one_pending');
        Schema::dropIfExists('gametables_campaign_join_requests');
    }
};
