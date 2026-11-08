<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('franjas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')->constrained('zonas')->cascadeOnDelete();
            $table->unsignedTinyInteger('dia_semana')->nullable()->comment('0 = domingo … 6 = sábado; null = todos los días');
            $table->time('hora_inicio');
            $table->time('hora_fin')->comment('Si es menor que hora_inicio, la franja cruza la medianoche');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('franjas');
    }
};
