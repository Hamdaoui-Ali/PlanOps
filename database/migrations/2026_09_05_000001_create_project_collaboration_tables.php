<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 16);
            $table->timestampTz('joined_at')->useCurrent();
            $table->timestampTz('removed_at')->nullable();
            $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();

            $table->unique(['project_id', 'user_id']);
            $table->index('project_id');
            $table->index('user_id');
            $table->index(['project_id', 'role']);
            $table->index(['user_id', 'project_id']);
            $table->index(['project_id', 'removed_at']);
        });

        Schema::create('project_invitations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('email', 320);
            $table->string('normalized_email', 320);
            $table->string('role', 16);
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('token_hash', 64);
            $table->timestampTz('expires_at');
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestampTz('last_sent_at')->nullable();
            $table->timestampsTz();

            $table->index('project_id');
            $table->index('email');
            $table->unique('token_hash');
            $table->index('expires_at');
        });

        Schema::create('project_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64);
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['project_id', 'created_at']);
            $table->index(['actor_user_id', 'created_at']);
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("CREATE UNIQUE INDEX project_memberships_one_active_owner_unique ON project_memberships (project_id) WHERE role = 'OWNER' AND removed_at IS NULL");
            DB::statement("CREATE UNIQUE INDEX project_invitations_pending_email_unique ON project_invitations (project_id, normalized_email) WHERE accepted_at IS NULL AND revoked_at IS NULL");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('project_events');
        Schema::dropIfExists('project_invitations');
        Schema::dropIfExists('project_memberships');
    }
};
