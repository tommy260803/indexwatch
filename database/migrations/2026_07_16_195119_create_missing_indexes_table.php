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
        Schema::create('missing_indexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('schema_name')->default('dbo');
            $table->string('table_name');
            $table->unsignedBigInteger('object_id');
            $table->unsignedBigInteger('index_group_handle');
            $table->jsonb('equality_columns')->nullable();
            $table->jsonb('inequality_columns')->nullable();
            $table->jsonb('included_columns')->nullable();
            $table->decimal('estimated_impact', 8, 2)->nullable();
            $table->unsignedBigInteger('user_seeks')->default(0);
            $table->unsignedBigInteger('user_scans')->default(0);
            $table->string('fingerprint'); // tabla + columnas normalizadas, para no repetir sugerencia
            $table->string('status')->default('candidate');
            $table->timestamp('last_seen_at');
            $table->timestamps();

            $table->unique(['server_id', 'fingerprint'], 'missing_indexes_fingerprint_unique');
        });

        DB::statement("ALTER TABLE missing_indexes ADD CONSTRAINT chk_missing_indexes_status CHECK (status IN ('candidate','reviewed','created','dismissed','stale'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missing_indexes');
    }
};
