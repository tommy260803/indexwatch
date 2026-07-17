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
        Schema::create('generated_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('server_id')->nullable()->constrained()->cascadeOnDelete();
            $table->jsonb('filters')->nullable();
            $table->string('format');
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        DB::statement("ALTER TABLE generated_reports ADD CONSTRAINT chk_reports_format CHECK (format IN ('pdf','xlsx'))");
        DB::statement("ALTER TABLE generated_reports ADD CONSTRAINT chk_reports_status CHECK (status IN ('pending','processing','ready','failed','expired'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('generated_reports');
    }
};
