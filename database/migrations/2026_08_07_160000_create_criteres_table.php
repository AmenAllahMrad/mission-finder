<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('criteres', function (Blueprint $table) {
            $table->id();

            $table->string('code')->unique();

            $table->string('label');

            $table->string('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('criteres');
    }
};