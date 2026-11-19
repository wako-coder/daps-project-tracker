<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a priority (low / medium / high) to every task.
 *
 * Done in three steps so that existing rows are handled explicitly rather than
 * by a column default: add the column as nullable, backfill every existing
 * task to 'medium', then make it required. Laravel runs the migration inside
 * one transaction on PostgreSQL, so a failure part way leaves the table as it
 * was.
 *
 * down() removes the constraint and the column. Only the priorities chosen
 * after the deploy are lost; every other column is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority', 10)->nullable()->after('status');
        });

        DB::table('tasks')->whereNull('priority')->update(['priority' => 'medium']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->string('priority', 10)->default('medium')->nullable(false)->change();
            $table->index(['project_id', 'priority']);
        });

        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_priority_check CHECK (priority IN ('low', 'medium', 'high'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE tasks DROP CONSTRAINT IF EXISTS tasks_priority_check');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['project_id', 'priority']);
            $table->dropColumn('priority');
        });
    }
};
