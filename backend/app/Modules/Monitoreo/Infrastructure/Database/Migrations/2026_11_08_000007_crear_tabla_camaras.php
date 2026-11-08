<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('camaras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')->constrained('zonas');
            $table->string('nombre', 40)->unique();
            $table->string('url_stream', 255)->nullable();
            $table->enum('estado', ['activa', 'sin_senal', 'inactiva'])->default('activa');
            $table->timestamp('ultima_conexion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('camaras');
    }
};
