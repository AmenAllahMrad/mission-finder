<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alertes_missions_envoyees', function (Blueprint $table) {
            $table->id();

            $table->foreignId('alerte_id')
                ->constrained('alertes')
                ->cascadeOnDelete();

            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->timestamp('envoyee_le');

            $table->unique(
                ['alerte_id', 'mission_id'],
                'alerte_mission_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alertes_missions_envoyees');
    }
};