<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Pending email invitations to an organization.
 *
 * Purely additive: it creates one new table and touches nothing that exists.
 * down() drops the table, so the feature can be removed without affecting
 * users, memberships, projects or tasks.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('email');
            $table->string('role', 20);
            // Only a SHA-256 hash of the link's token is stored, like a password.
            $table->string('token_hash', 64)->unique();
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamps();

            // One pending invitation per address per organization.
            $table->unique(['organization_id', 'email']);
        });

        DB::statement("ALTER TABLE invitations ADD CONSTRAINT invitations_role_check CHECK (role IN ('admin', 'member'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
