<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            // Nullable: v2 permite alertas sin índice existente (missing index, heap,
            // estadística desactualizada). Usar subject_type/subject_id en esos casos.
            $table->foreignId('sql_index_id')->nullable()->constrained('sql_indexes')->nullOnDelete();
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            // hash(server_id + alert_type + subject), ver Alert::makeFingerprint()
            $table->string('fingerprint');
            $table->string('alert_type');
            $table->string('severity');
            $table->string('status')->default('pending');
            $table->string('recommended_action')->nullable();
            $table->decimal('fragmentation_percent', 5, 2)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->string('whatsapp_message_id')->nullable();
            $table->foreignId('responded_by_contact_id')->nullable()->constrained('authorized_contacts')->nullOnDelete();
            $table->string('responded_action')->nullable();
            $table->foreignId('approved_by_contact_id')->nullable()->constrained('authorized_contacts')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index('fingerprint');
            $table->index(['subject_type', 'subject_id']);
        });

        DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alerts_severity CHECK (severity IN ('info','warning','critical'))");
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alerts_status CHECK (status IN (
            'pending','sent','awaiting_response','approved','scheduled',
            'running','succeeded','failed','expired','dismissed'
        ))");
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alerts_type CHECK (alert_type IN (
            'fragmentation','inactive','missing_index','duplicate_index',
            'heap','stale_statistics','page_splits','fill_factor'
        ))");
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alerts_recommended_action CHECK (recommended_action IS NULL OR recommended_action IN (
            'REBUILD','REORGANIZE','UPDATE STATISTICS','CREATE INDEX','DISABLE INDEX',
            'DROP INDEX','CREATE CLUSTERED','REVIEW','IGNORE'
        ))");
        DB::statement("ALTER TABLE alerts ADD CONSTRAINT chk_alerts_responded_action CHECK (responded_action IS NULL OR responded_action IN (
            'REBUILD','REORGANIZE','UPDATE STATISTICS','CREATE INDEX','DISABLE INDEX',
            'DROP INDEX','CREATE CLUSTERED','REVIEW','IGNORE'
        ))");

        // Índice único PARCIAL: solo puede haber una alerta ABIERTA por fingerprint.
        // Una vez cerrada (succeeded/failed/expired/dismissed), el mismo fingerprint
        // puede volver a generar una alerta nueva en el futuro sin violar la unicidad.
        DB::statement("
            CREATE UNIQUE INDEX alerts_open_fingerprint_unique
            ON alerts (fingerprint)
            WHERE status IN ('pending','sent','awaiting_response','approved','scheduled','running')
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
    }
};