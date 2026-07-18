<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_scan_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->uuid('correlation_id')->unique();
            $table->string('status')->default('running');
            $table->jsonb('capabilities')->nullable();
            $table->jsonb('metrics')->nullable();
            $table->jsonb('warnings')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedBigInteger('duration_ms')->nullable();
            $table->timestamps();

            $table->index(['server_id', 'started_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE server_scan_runs ADD CONSTRAINT chk_server_scan_runs_status CHECK (status IN ('running','success','degraded','error'))");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('server_scan_runs');
    }
};
