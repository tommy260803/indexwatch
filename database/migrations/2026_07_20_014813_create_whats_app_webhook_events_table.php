<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whats_app_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique(); // Meta message ID for idempotency
            $table->string('from'); // WhatsApp phone number (E.164)
            $table->string('action'); // action taken (rebuild, reorganize, etc.)
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('authorized_contacts')->nullOnDelete();
            $table->jsonb('payload')->nullable(); // full webhook payload for debugging
            $table->timestamps();

            $table->index(['alert_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whats_app_webhook_events');
    }
};