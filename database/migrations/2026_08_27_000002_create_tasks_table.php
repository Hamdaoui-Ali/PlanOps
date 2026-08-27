<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->unsignedBigInteger('number');
            $table->string('title', 300);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('NOT_STARTED');
            $table->string('priority', 32)->default('MEDIUM');
            $table->date('due_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestampTz('first_started_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampTz('status_changed_at')->useCurrent();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->unique(['project_id', 'number']);
            $table->index(['user_id', 'status']);
            $table->index(['project_id', 'status', 'position']);
            $table->index(['project_id', 'parent_task_id']);
            $table->index(['user_id', 'priority']);
            $table->index(['user_id', 'due_on']);
            $table->index(['user_id', 'updated_at']);
            $table->index(['parent_task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
