<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_game', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_key_id')->constrained('room_keys')->cascadeOnDelete();
            $table->string('game', 20);
            $table->timestamps();

            $table->unique(['room_key_id', 'game']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_game');
    }
};
