<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_sources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('source_id')
                ->constrained('sources')
                ->cascadeOnDelete();

            $table->string('url_origine');

            $table->json('raw_data')->nullable();

            $table->timestamp('derniere_detection')
                ->nullable();

            $table->unique(
                ['mission_id', 'source_id'],
                'mission_source_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_sources');
    }
};