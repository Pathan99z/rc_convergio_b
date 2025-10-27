<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_integrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('email_address')->index(); // support@fincompany.com
            $table->string('provider')->default('gmail'); // gmail, outlook, imap
            $table->json('credentials')->nullable(); // Encrypted OAuth tokens or IMAP settings
            $table->boolean('is_active')->default(true);
            $table->boolean('auto_create_tickets')->default(true);
            $table->string('default_priority')->default('medium'); // low, medium, high, urgent
            $table->unsignedBigInteger('default_assignee_id')->nullable();
            $table->unsignedBigInteger('default_team_id')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->integer('tickets_created_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('default_assignee_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('default_team_id')->references('id')->on('teams')->onDelete('set null');

            // Unique constraint - one integration per email per tenant
            $table->unique(['tenant_id', 'email_address']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_integrations');
    }
};
