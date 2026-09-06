<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planops_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 80);
            $table->string('idempotency_key', 255)->unique();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type', 80)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->json('data');
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();

            $table->index(['recipient_id', 'read_at', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('planops_notifications');
    }
};
