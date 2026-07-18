<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistics_status', function (Blueprint $table) {
            $table->decimal('modification_ratio', 19, 4)->nullable()->change();
        });

        Schema::table('index_snapshots', function (Blueprint $table) {
            $table->foreignId('server_scan_run_id')
                ->nullable()
                ->after('id')
                ->constrained('server_scan_runs')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('seeks')->nullable()->change();
            $table->unsignedBigInteger('scans')->nullable()->change();
            $table->unsignedBigInteger('lookups')->nullable()->change();
            $table->unsignedBigInteger('writes')->nullable()->change();
            $table->unique(['server_scan_run_id', 'sql_index_id'], 'index_snapshots_scan_index_unique');
        });

        Schema::table('index_operational_snapshots', function (Blueprint $table) {
            $table->foreignId('server_scan_run_id')
                ->nullable()
                ->after('id')
                ->constrained('server_scan_runs')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('elapsed_seconds')->nullable();
            $table->decimal('page_splits_per_minute', 19, 4)->nullable();
            $table->unique(['server_scan_run_id', 'sql_index_id'], 'index_operational_scan_index_unique');
        });
    }

    public function down(): void
    {
        Schema::table('index_operational_snapshots', function (Blueprint $table) {
            $table->dropUnique('index_operational_scan_index_unique');
            $table->dropConstrainedForeignId('server_scan_run_id');
            $table->dropColumn(['elapsed_seconds', 'page_splits_per_minute']);
        });

        DB::table('index_snapshots')->whereNull('seeks')->update(['seeks' => 0]);
        DB::table('index_snapshots')->whereNull('scans')->update(['scans' => 0]);
        DB::table('index_snapshots')->whereNull('lookups')->update(['lookups' => 0]);
        DB::table('index_snapshots')->whereNull('writes')->update(['writes' => 0]);

        Schema::table('index_snapshots', function (Blueprint $table) {
            $table->dropUnique('index_snapshots_scan_index_unique');
            $table->dropConstrainedForeignId('server_scan_run_id');
            $table->unsignedBigInteger('seeks')->nullable(false)->default(0)->change();
            $table->unsignedBigInteger('scans')->nullable(false)->default(0)->change();
            $table->unsignedBigInteger('lookups')->nullable(false)->default(0)->change();
            $table->unsignedBigInteger('writes')->nullable(false)->default(0)->change();
        });

        DB::table('statistics_status')
            ->where('modification_ratio', '>', 999.9999)
            ->update(['modification_ratio' => 999.9999]);

        Schema::table('statistics_status', function (Blueprint $table) {
            $table->decimal('modification_ratio', 7, 4)->nullable()->change();
        });
    }
};
