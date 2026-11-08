<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unidades', function (Blueprint $table) {
            $table->id();
            $table->string('torre', 5);
            $table->string('numero', 10);
            $table->timestamps();

            $table->unique(['torre', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unidades');
    }
};
