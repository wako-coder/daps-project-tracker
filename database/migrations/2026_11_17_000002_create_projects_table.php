<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->bigInteger('budget_santim');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE projects ADD CONSTRAINT projects_budget_santim_check CHECK (budget_santim >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
