<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('key', 10);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('PLANNED');
            $table->string('color', 32)->nullable();
            $table->string('icon', 64)->nullable();
            $table->date('start_on')->nullable();
            $table->date('target_on')->nullable();
            $table->unsignedBigInteger('next_task_number')->default(1);
            $table->timestampTz('archived_at')->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'key']);
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'archived_at']);
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
