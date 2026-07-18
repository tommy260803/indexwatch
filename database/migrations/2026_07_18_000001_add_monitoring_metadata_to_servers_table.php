<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('servers', function (Blueprint $table) {
            $table->string('sql_server_version')->nullable();
            $table->string('sql_server_edition')->nullable();
            $table->jsonb('sql_server_capabilities')->nullable();
            $table->timestamp('sql_server_started_at')->nullable();
            $table->string('health_score_version')->nullable();
            $table->jsonb('health_score_details')->nullable();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE servers DROP CONSTRAINT IF EXISTS chk_servers_last_scan_status');
            DB::statement("ALTER TABLE servers ADD CONSTRAINT chk_servers_last_scan_status CHECK (last_scan_status IS NULL OR last_scan_status IN ('success','error','running','degraded'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE servers DROP CONSTRAINT IF EXISTS chk_servers_last_scan_status');
            DB::statement("UPDATE servers SET last_scan_status = 'error' WHERE last_scan_status = 'degraded'");
            DB::statement("ALTER TABLE servers ADD CONSTRAINT chk_servers_last_scan_status CHECK (last_scan_status IS NULL OR last_scan_status IN ('success','error','running'))");
        }

        Schema::table('servers', function (Blueprint $table) {
            $table->dropColumn([
                'sql_server_version',
                'sql_server_edition',
                'sql_server_capabilities',
                'sql_server_started_at',
                'health_score_version',
                'health_score_details',
            ]);
        });
    }
};
