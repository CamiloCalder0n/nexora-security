<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registros_porteria', function (Blueprint $table) {
            $table->id();
            $table->enum('tipo_ingreso', ['visitante', 'domiciliario', 'vehiculo', 'contratista']);
            $table->string('nombre_visitante', 120);
            $table->string('documento', 20);
            $table->string('placa', 10)->nullable();
            $table->unsignedSmallInteger('cantidad_personas')->default(1);
            $table->foreignId('unidad_id')->constrained('unidades');
            $table->foreignId('zona_id')->constrained('zonas');
            $table->foreignId('registrado_por')->constrained('users');
            $table->foreignId('autorizacion_id')->nullable()->unique()->constrained('autorizaciones');
            $table->timestamp('fecha_ingreso');
            $table->timestamp('fecha_salida')->nullable();
            $table->timestamps();

            $table->index(['zona_id', 'fecha_ingreso']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registros_porteria');
    }
};
