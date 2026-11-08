<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('telefono', 20)->nullable()->after('email');
            $table->string('telegram_chat_id', 32)->nullable()->after('telefono');
            $table->string('telegram_codigo', 6)->nullable()->unique()->after('telegram_chat_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['telegram_codigo']);
            $table->dropColumn(['telefono', 'telegram_chat_id', 'telegram_codigo']);
        });
    }
};
