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
        Schema::create('sql_indexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('schema_name')->default('dbo');
            $table->string('table_name');
            $table->string('index_name');
            // Identificadores nativos de SQL Server (sys.objects / sys.indexes).
            // La combinación server_id + object_id + index_id_native es la identidad
            // ESTABLE del índice: sobrevive a un rename del índice o la tabla.
            $table->unsignedBigInteger('object_id');
            $table->unsignedInteger('index_id_native');
            $table->string('type')->default('NONCLUSTERED');
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_primary_key')->default(false);
            $table->boolean('is_disabled')->default(false);

            // Estado físico cacheado del último escaneo (evita join a index_snapshots
            // en listados del dashboard; el histórico real sigue viviendo en index_snapshots).
            $table->decimal('fragmentation_percent', 5, 2)->nullable();
            $table->decimal('size_mb', 12, 2)->nullable();
            $table->unsignedBigInteger('page_count')->nullable();

            // Fill factor (F06)
            $table->unsignedTinyInteger('fill_factor')->nullable();
            $table->unsignedTinyInteger('optimal_fill_factor')->nullable();
            $table->string('fill_factor_reason')->nullable();

            // Uso acumulado (F09)
            $table->unsignedBigInteger('user_seeks')->default(0);
            $table->unsignedBigInteger('user_scans')->default(0);
            $table->unsignedBigInteger('user_lookups')->default(0);
            $table->unsignedBigInteger('user_updates')->default(0);
            $table->timestamp('last_user_seek_at')->nullable();
            $table->timestamp('last_user_scan_at')->nullable();
            $table->timestamp('last_user_lookup_at')->nullable();

            // Detecta reinicio de contadores DMV (SQL Server los resetea en restart).
            $table->timestamp('usage_stats_since')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique(['server_id', 'object_id', 'index_id_native'], 'sql_indexes_stable_identity_unique');
            $table->index(['server_id', 'table_name']);
        });

        DB::statement("ALTER TABLE sql_indexes ADD CONSTRAINT chk_sql_indexes_status CHECK (status IN ('active','dropped'))");
        DB::statement('ALTER TABLE sql_indexes ADD CONSTRAINT chk_sql_indexes_fragmentation CHECK (fragmentation_percent IS NULL OR (fragmentation_percent >= 0 AND fragmentation_percent <= 100))');
        DB::statement('ALTER TABLE sql_indexes ADD CONSTRAINT chk_sql_indexes_fill_factor CHECK (fill_factor IS NULL OR (fill_factor >= 1 AND fill_factor <= 100))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE sql_indexes DROP CONSTRAINT IF EXISTS chk_sql_indexes_fragmentation');
        DB::statement('ALTER TABLE sql_indexes DROP CONSTRAINT IF EXISTS chk_sql_indexes_fill_factor');
        DB::statement('ALTER TABLE sql_indexes DROP CONSTRAINT IF EXISTS chk_sql_indexes_status');
        
        Schema::dropIfExists('sql_indexes');
    }
};
