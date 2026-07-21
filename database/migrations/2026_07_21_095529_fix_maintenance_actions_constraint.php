<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old constraint that doesn't include REVIEW and IGNORE
        DB::statement('ALTER TABLE maintenance_actions DROP CONSTRAINT chk_maint_action_type');

        // Add the new constraint that includes all RecommendedAction enum values
        DB::statement("ALTER TABLE maintenance_actions ADD CONSTRAINT chk_maint_action_type CHECK (action_type IN (
            'REBUILD','REORGANIZE','UPDATE STATISTICS','CREATE INDEX','DISABLE INDEX','DROP INDEX','CREATE CLUSTERED','REVIEW','IGNORE'
        ))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop the new constraint
        DB::statement('ALTER TABLE maintenance_actions DROP CONSTRAINT chk_maint_action_type');

        // Restore the old constraint
        DB::statement("ALTER TABLE maintenance_actions ADD CONSTRAINT chk_maint_action_type CHECK (action_type IN (
            'REBUILD','REORGANIZE','UPDATE STATISTICS','CREATE INDEX','DISABLE INDEX','DROP INDEX','CREATE CLUSTERED'
        ))");
    }
};
