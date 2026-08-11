<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scores_missions_profils', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('profil_recherche_id')
                ->constrained('profils_recherche')
                ->cascadeOnDelete();

            $table->integer('score')->default(0);

            $table->timestamp('calcule_le')->nullable();

            $table->unique(
                ['mission_id', 'profil_recherche_id'],
                'score_mission_profil_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scores_missions_profils');
    }
};