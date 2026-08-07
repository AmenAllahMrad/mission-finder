<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regles_scoring', function (Blueprint $table) {
            $table->id();

            $table->foreignId('profil_recherche_id')
                ->constrained('profils_recherche')
                ->cascadeOnDelete();

            $table->foreignId('critere_id')
                ->constrained('criteres')
                ->cascadeOnDelete();

            $table->integer('poids')->default(1);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regles_scoring');
    }
};