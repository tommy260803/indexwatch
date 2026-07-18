<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('index_operational_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sql_index_id')->constrained('sql_indexes')->cascadeOnDelete();
            $table->unsignedBigInteger('leaf_page_split_count');
            $table->unsignedBigInteger('nonleaf_page_split_count');
            $table->unsignedBigInteger('page_split_count');
            $table->unsignedBigInteger('page_split_delta')->nullable();
            $table->timestamp('sampled_at');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['sql_index_id', 'sampled_at'], 'index_operational_samples_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('index_operational_snapshots');
    }
};
