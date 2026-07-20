<?php
//create maintenance actions table
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('maintenance_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_id')->constrained()->cascadeOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->cascadeOnDelete();
            // Nullable: v2 permite acciones como CREATE INDEX sobre un índice que aún no existe.
            $table->foreignId('sql_index_id')->nullable()->constrained('sql_indexes')->nullOnDelete();
            $table->string('action_type');
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_for')->nullable();
            $table->timestamp('started_at')->nullable();
            // Script T-SQL generado; se guarda para auditoría/futura ejecución real.
            $table->text('sql_script')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->text('error')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('initiated_by_contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->timestamps();

            $table->index('server_id');
            $table->index('scheduled_for');
        });

        DB::statement("ALTER TABLE maintenance_actions ADD CONSTRAINT chk_maint_action_type CHECK (action_type IN (
            'REBUILD','REORGANIZE','UPDATE STATISTICS','CREATE INDEX','DISABLE INDEX','DROP INDEX','CREATE CLUSTERED'
        ))");
        DB::statement("ALTER TABLE maintenance_actions ADD CONSTRAINT chk_maint_status CHECK (status IN ('pending','scheduled','running','completed','failed','cancelled'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('maintenance_actions');
    }
};