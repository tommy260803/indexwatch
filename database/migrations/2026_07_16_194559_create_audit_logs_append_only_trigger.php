<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_audit_log_mutation() RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'audit_logs es append-only: operación % no permitida', TG_OP;
                END;
                $$ LANGUAGE plpgsql;

                CREATE TRIGGER trg_audit_logs_no_update
                BEFORE UPDATE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation();

                CREATE TRIGGER trg_audit_logs_no_delete
                BEFORE DELETE ON audit_logs
                FOR EACH ROW EXECUTE FUNCTION prevent_audit_log_mutation();
            SQL);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS trg_audit_logs_no_update ON audit_logs;
                DROP TRIGGER IF EXISTS trg_audit_logs_no_delete ON audit_logs;
                DROP FUNCTION IF EXISTS prevent_audit_log_mutation();
            SQL);
        }
    }
};
