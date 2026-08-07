<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('source_id')
                ->constrained('sources');

            $table->string('titre');

            $table->text('description')->nullable();

            $table->string('entreprise')->nullable();

            $table->decimal('tjm_min', 8, 2)->nullable();
            $table->decimal('tjm_max', 8, 2)->nullable();

            $table->string('remote_type')->nullable();

            $table->string('localisation')->nullable();

            $table->integer('duree_mois')->nullable();

            $table->date('date_publication')->nullable();

            $table->string('url_origine');

            $table->string('hash_unique')->unique();

            $table->json('raw_data')->nullable();

            $table->integer('score')->default(0);

            $table->string('statut')->default('nouveau');

            $table->timestamp('date_candidature')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};