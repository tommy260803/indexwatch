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
        Schema::create('statistics_status', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->string('schema_name')->default('dbo');
            $table->string('table_name');
            $table->unsignedBigInteger('object_id');
            $table->unsignedInteger('stats_id');
            $table->string('stats_name');
            $table->unsignedBigInteger('row_count')->default(0);
            $table->unsignedBigInteger('modification_count')->default(0);
            // Calculado en el servicio de análisis (protegido contra división por cero ahí).
            $table->decimal('modification_ratio', 7, 4)->nullable();
            $table->timestamp('last_updated_at')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->unique(['server_id', 'object_id', 'stats_id'], 'statistics_status_stable_identity_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('statistics_status');
    }
};
