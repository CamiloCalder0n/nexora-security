<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('autorizaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unidad_id')->constrained('unidades');
            $table->foreignId('residente_id')->constrained('residentes');
            $table->string('visitante_nombre', 120);
            $table->string('visitante_documento', 20);
            $table->string('placa', 10)->nullable();
            $table->timestamp('valida_desde');
            $table->timestamp('valida_hasta');
            $table->enum('estado', ['vigente', 'usada', 'anulada', 'expirada'])->default('vigente');
            $table->timestamp('usada_at')->nullable();
            $table->timestamps();

            $table->index(['estado', 'valida_hasta']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('autorizaciones');
    }
};
