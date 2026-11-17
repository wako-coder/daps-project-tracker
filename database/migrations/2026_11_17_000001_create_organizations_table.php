<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        // A membership row is the only thing that grants access to an organization.
        Schema::create('organization_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
            $table->index('user_id');
        });

        DB::statement("ALTER TABLE organization_user ADD CONSTRAINT organization_user_role_check CHECK (role IN ('admin', 'member'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
