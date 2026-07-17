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
        Schema::create('maintenance_windows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('server_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('day_of_week'); // 0=domingo ... 6=sábado
            $table->time('start_time');
            $table->time('end_time');
            $table->string('timezone')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
        
        DB::statement('ALTER TABLE maintenance_windows ADD CONSTRAINT chk_maint_windows_day CHECK (day_of_week >= 0 AND day_of_week <= 6)');
        DB::statement('ALTER TABLE maintenance_windows ADD CONSTRAINT chk_maint_windows_range CHECK (end_time > start_time)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('maintenance_windows');
    }
};
