<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->bigInteger('estimated_cost_santim');
            $table->string('status', 20)->default('todo');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE tasks ADD CONSTRAINT tasks_estimated_cost_santim_check CHECK (estimated_cost_santim >= 0)');
        DB::statement("ALTER TABLE tasks ADD CONSTRAINT tasks_status_check CHECK (status IN ('todo', 'in_progress', 'done'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
