<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profil_recherche_id')
                ->constrained('profils_recherche')
                ->cascadeOnDelete();

            $table->string('canal');

            $table->string('destination');

            $table->string('frequence');

            $table->integer('seuil_score_min')->default(0);

            $table->boolean('actif')->default(true);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes');
    }
};