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
        Schema::create('indexes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained('servers')->cascadeOnDelete();
            $table->string('table_name');
            $table->string('index_name');
            $table->decimal('fragmentation_percent', 5, 2)->default(0);
            $table->unsignedBigInteger('size_mb')->default(0);
            $table->unsignedBigInteger('seeks')->default(0);
            $table->unsignedBigInteger('scans')->default(0);
            $table->unsignedBigInteger('lookups')->default(0);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indexes');
    }
};
