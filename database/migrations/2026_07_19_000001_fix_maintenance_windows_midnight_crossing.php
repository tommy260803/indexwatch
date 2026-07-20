<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'maintenance_windows';

    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->recreateWithoutRangeConstraint();
        } else {
            DB::statement('ALTER TABLE maintenance_windows DROP CONSTRAINT IF EXISTS chk_maint_windows_range');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->recreateWithRangeConstraint();
        } else {
            DB::statement(
                'ALTER TABLE maintenance_windows ADD CONSTRAINT chk_maint_windows_range CHECK (end_time > start_time)'
            );
        }
    }

    private function recreateWithoutRangeConstraint(): void
    {
        DB::beginTransaction();

        DB::statement('CREATE TABLE maintenance_windows_tmp ('
            .'"id" integer primary key autoincrement not null,'
            .'"server_id" integer not null references "servers" ("id") on delete cascade,'
            .'"day_of_week" integer not null,'
            .'"start_time" time not null,'
            .'"end_time" time not null,'
            .'"timezone" varchar,'
            .'"active" boolean not null default 1,'
            .'"created_at" datetime,'
            .'"updated_at" datetime'
        .')');

        DB::statement(
            'INSERT INTO maintenance_windows_tmp '
            .'("id","server_id","day_of_week","start_time","end_time","timezone","active","created_at","updated_at") '
            .'SELECT "id","server_id","day_of_week","start_time","end_time","timezone","active","created_at","updated_at" '
            .'FROM maintenance_windows'
        );

        DB::statement('DROP TABLE maintenance_windows');
        DB::statement('ALTER TABLE maintenance_windows_tmp RENAME TO maintenance_windows');
        DB::statement('CREATE INDEX maintenance_windows_server_id_index ON maintenance_windows ("server_id")');
        DB::statement('CREATE INDEX maintenance_windows_scheduled_for_index ON maintenance_windows ("day_of_week")');

        DB::commit();
    }

    private function recreateWithRangeConstraint(): void
    {
        DB::beginTransaction();

        DB::statement('CREATE TABLE maintenance_windows_tmp ('
            .'"id" integer primary key autoincrement not null,'
            .'"server_id" integer not null references "servers" ("id") on delete cascade,'
            .'"day_of_week" integer not null CHECK ("day_of_week" >= 0 AND "day_of_week" <= 6),'
            .'"start_time" time not null,'
            .'"end_time" time not null CHECK ("end_time" > "start_time"),'
            .'"timezone" varchar,'
            .'"active" boolean not null default 1,'
            .'"created_at" datetime,'
            .'"updated_at" datetime'
        .')');

        DB::statement(
            'INSERT INTO maintenance_windows_tmp '
            .'("id","server_id","day_of_week","start_time","end_time","timezone","active","created_at","updated_at") '
            .'SELECT "id","server_id","day_of_week","start_time","end_time","timezone","active","created_at","updated_at" '
            .'FROM maintenance_windows'
        );

        DB::statement('DROP TABLE maintenance_windows');
        DB::statement('ALTER TABLE maintenance_windows_tmp RENAME TO maintenance_windows');
        DB::statement('CREATE INDEX maintenance_windows_server_id_index ON maintenance_windows ("server_id")');
        DB::statement('CREATE INDEX maintenance_windows_scheduled_for_index ON maintenance_windows ("day_of_week")');

        DB::commit();
    }
};
