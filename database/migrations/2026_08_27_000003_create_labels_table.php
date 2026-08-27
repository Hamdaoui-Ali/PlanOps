<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('normalized_name', 80);
            $table->string('color', 32)->nullable();
            $table->timestampsTz();

            $table->unique(['user_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labels');
    }
};
