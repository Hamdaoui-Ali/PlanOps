<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->foreignId('owner_id')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('created_by_user_id')->nullable()->after('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assignee_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            $table->index(['project_id', 'assignee_id', 'status']);
        });

        Schema::table('task_activities', function (Blueprint $table): void {
            $table->foreignId('actor_user_id')->nullable()->after('user_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('labels', function (Blueprint $table): void {
            $table->foreignId('project_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
            $table->index('project_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestampTz('deactivated_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table): Blueprint => $table->dropColumn('deactivated_at'));
        Schema::table('labels', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
            $table->dropIndex(['project_id']);
            $table->dropColumn('project_id');
        });
        Schema::table('task_activities', function (Blueprint $table): void {
            $table->dropForeign(['actor_user_id']);
            $table->dropColumn('actor_user_id');
        });
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'assignee_id', 'status']);
            $table->dropForeign(['assignee_id']);
            $table->dropForeign(['created_by_user_id']);
            $table->dropColumn(['assignee_id', 'created_by_user_id']);
        });
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropForeign(['owner_id']);
            $table->dropColumn('owner_id');
        });
    }
};
