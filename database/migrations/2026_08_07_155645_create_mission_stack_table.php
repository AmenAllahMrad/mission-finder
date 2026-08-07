<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_stack', function (Blueprint $table) {
            $table->foreignId('mission_id')
                ->constrained('missions')
                ->cascadeOnDelete();

            $table->foreignId('stack_id')
                ->constrained('stacks')
                ->cascadeOnDelete();

            $table->primary(['mission_id', 'stack_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_stack');
    }
};