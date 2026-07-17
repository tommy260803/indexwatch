<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('servers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedInteger('port')->default(1433);
            $table->jsonb('connection_options')->nullable();
            $table->string('database_name');
            $table->string('username');
            // Cifrado vía cast 'encrypted' en el modelo Server; aquí solo texto largo.
            $table->text('password');
            $table->string('status')->default('active');
            $table->decimal('warning_threshold', 5, 2)->default(5.00);
            $table->decimal('critical_threshold', 5, 2)->default(30.00);
            $table->unsignedTinyInteger('health_score')->nullable();
            $table->timestamp('health_score_updated_at')->nullable();
            $table->decimal('stats_stale_threshold', 5, 2)->default(20.00);
            $table->unsignedInteger('minimum_index_pages')->default(1000);
            $table->string('timezone')->default('America/Lima');
            $table->timestamp('last_scanned_at')->nullable();
            $table->string('last_scan_status')->nullable();
            $table->text('last_scan_error')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });

        // Reglas de negocio reforzadas a nivel de base de datos (defensa en profundidad,
        // además de la validación en el FormRequest).
        DB::statement("ALTER TABLE servers ADD CONSTRAINT chk_servers_status CHECK (status IN ('active','inactive','maintenance'))");
        DB::statement('ALTER TABLE servers ADD CONSTRAINT chk_servers_thresholds CHECK (critical_threshold > warning_threshold)');
        DB::statement('ALTER TABLE servers ADD CONSTRAINT chk_servers_threshold_range CHECK (warning_threshold >= 0 AND critical_threshold <= 100)');
        DB::statement("ALTER TABLE servers ADD CONSTRAINT chk_servers_health_score CHECK (health_score IS NULL OR (health_score >= 0 AND health_score <= 100))");
        DB::statement("ALTER TABLE servers ADD CONSTRAINT chk_servers_last_scan_status CHECK (last_scan_status IS NULL OR last_scan_status IN ('success','error','running'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('servers');
    }
};