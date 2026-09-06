<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labels', function (Blueprint $table): void {
            $table->dropUnique('labels_user_id_normalized_name_unique');
            $table->unique(['project_id', 'normalized_name']);
        });
    }

    public function down(): void
    {
        Schema::table('labels', function (Blueprint $table): void {
            $table->dropUnique('labels_project_id_normalized_name_unique');
            $table->unique(['user_id', 'normalized_name']);
        });
    }
};
