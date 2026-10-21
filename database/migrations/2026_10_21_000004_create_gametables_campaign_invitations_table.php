<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gametables_campaign_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('campaign_id');
            $table->string('email', 255);
            $table->uuid('invited_by_user_id')->nullable();
            $table->string('inviter_name', 255);
            $table->string('token_hash', 64)->unique();
            $table->dateTime('expires_at');
            $table->unsignedSmallInteger('send_count');
            $table->dateTime('last_sent_at');
            $table->timestamps();

            $table->foreign('campaign_id')->references('id')->on('gametables_campaigns')->onDelete('cascade');
            $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();

            $table->unique(['campaign_id', 'email']);
            $table->index('email');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gametables_campaign_invitations');
    }
};
