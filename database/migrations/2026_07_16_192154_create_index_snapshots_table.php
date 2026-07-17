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
        Schema::create('index_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sql_index_id')->constrained('sql_indexes')->cascadeOnDelete();
            $table->decimal('fragmentation_percent', 5, 2);
            $table->decimal('size_mb', 12, 2)->nullable();
            $table->unsignedBigInteger('page_count')->nullable();
            $table->unsignedBigInteger('record_count')->nullable();
            // Estadísticas de uso (equivalentes a sys.dm_db_index_usage_stats),
            // presentes en el prototipo original. Igual que la fragmentación,
            // cambian con cada escaneo, así que se historizan aquí y no como
            // columna estática en sql_indexes.
            $table->unsignedBigInteger('seeks')->default(0);
            $table->unsignedBigInteger('scans')->default(0);
            $table->unsignedBigInteger('lookups')->default(0);
            $table->unsignedBigInteger('writes')->default(0);
            $table->unsignedTinyInteger('fill_factor')->nullable();
            $table->timestamp('index_last_used_at')->nullable();
            $table->timestamp('scanned_at');
            // Sin updated_at: cada fila es una fotografía inmutable de un escaneo.
            $table->timestamp('created_at')->useCurrent();
        });

        DB::statement('ALTER TABLE index_snapshots ADD CONSTRAINT chk_snapshots_fragmentation CHECK (fragmentation_percent >= 0 AND fragmentation_percent <= 100)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('index_snapshots');
    }
};
