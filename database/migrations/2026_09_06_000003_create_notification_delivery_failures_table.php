<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_delivery_failures', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 80);
            $table->unsignedBigInteger('recipient_id');
            $table->unsignedBigInteger('project_id')->nullable();
            $table->unsignedInteger('attempts')->default(1);
            $table->string('exception_class', 255);
            $table->string('message', 500)->nullable();
            $table->timestampsTz();

            $table->index(['recipient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_delivery_failures');
    }
};
