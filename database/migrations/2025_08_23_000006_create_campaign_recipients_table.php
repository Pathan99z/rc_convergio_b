<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_recipients', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('campaign_id');
            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('email');
            $table->string('name')->nullable();
            $table->string('status')->default('pending'); // pending, sent, delivered, opened, clicked, bounced, failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            $table->string('bounce_reason')->nullable();
            $table->string('message_id')->nullable(); // provider message ID
            $table->json('metadata')->nullable(); // additional provider data
            $table->unsignedBigInteger('tenant_id');
            $table->timestamps();

            $table->index('tenant_id');
            $table->index('campaign_id');
            $table->index('contact_id');
            $table->index('email');
            $table->index('status');
            $table->index('sent_at');
            $table->index('message_id');

            $table->foreign('campaign_id')->references('id')->on('campaigns');
            $table->foreign('contact_id')->references('id')->on('contacts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_recipients');
    }
};
