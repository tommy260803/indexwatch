<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->nullable()->constrained()->nullOnDelete();
            $table->string('auditable_type');
            $table->unsignedBigInteger('auditable_id');
            $table->string('action');
            $table->string('actor_type');
            $table->string('actor_identifier')->nullable();
            $table->string('actor_name')->nullable();
            $table->string('source')->nullable();
            $table->string('status')->nullable();
            $table->text('description')->nullable();
            $table->jsonb('payload')->nullable();
            $table->jsonb('metadata')->nullable();
            // Sin updated_at: se refuerza con trigger en la siguiente migración.
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('action');
            $table->index('source');
            $table->index('server_id');
        });

        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT chk_audit_logs_actor_type CHECK (actor_type IN ('whatsapp', 'system', 'user', 'api'))");
        DB::statement("ALTER TABLE audit_logs ADD CONSTRAINT chk_audit_logs_source CHECK (source IN ('webhook', 'cli', 'dashboard', 'scheduler', 'job'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
