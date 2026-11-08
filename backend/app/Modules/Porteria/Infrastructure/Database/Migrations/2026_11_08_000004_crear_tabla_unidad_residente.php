<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidad_residente', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades')->cascadeOnDelete();
            $table->foreignId('residente_id')->constrained('residentes')->cascadeOnDelete();
            $table->boolean('es_titular')->default(false);
            $table->timestamps();

            $table->unique(['unidad_id', 'residente_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidad_residente');
    }
};
