<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
  public function up(): void
{
    Schema::create('sources', function (Blueprint $table) {
        $table->id();

        $table->string('nom');

        $table->string('type');

        $table->string('url_base')->nullable();

        $table->string('parser_class');

        $table->integer('frequence_polling_minutes')
            ->default(60);

        $table->text('credentials')->nullable();

        $table->boolean('actif')
            ->default(true);

        $table->timestamp('derniere_execution')
            ->nullable();

        $table->string('dernier_statut')
            ->nullable();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sources');
    }
};
