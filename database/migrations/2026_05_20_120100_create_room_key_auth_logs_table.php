<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_key_auth_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_key_id')->constrained('room_keys')->cascadeOnDelete();
            $table->string('ip', 45);
            $table->string('city', 120)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();

            $table->index(['room_key_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_key_auth_logs');
    }
};
