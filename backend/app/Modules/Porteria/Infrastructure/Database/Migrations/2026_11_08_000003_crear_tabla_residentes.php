<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('residentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nombre', 120);
            $table->string('documento', 20)->unique();
            $table->string('telefono', 20)->nullable();
            $table->enum('tipo_residente', ['propietario', 'arrendatario']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('residentes');
    }
};
