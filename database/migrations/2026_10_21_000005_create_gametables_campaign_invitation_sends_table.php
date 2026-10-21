<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Send log behind the invitation limits. It keeps an HMAC of the address, never the address.
        Schema::create('gametables_campaign_invitation_sends', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->string('email_hash', 64);
            $table->string('kind', 10);
            $table->dateTime('occurred_at');

            $table->foreign('campaign_id')->references('id')->on('gametables_campaigns')->onDelete('cascade');

            $table->index(['campaign_id', 'email_hash', 'kind', 'occurred_at'], 'gametables_invitation_sends_lookup');
            $table->index('occurred_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_campaign_invitation_sends');
    }
};
