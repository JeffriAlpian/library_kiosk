<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CATATAN: tabel dinamai "lab_sessions" (bukan "sessions") karena Laravel 11+
// sudah punya tabel bawaan "sessions" untuk session login (driver database).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('computer_id')->constrained('computers')->cascadeOnDelete();
            $table->string('nama');
            $table->string('npm')->index();
            $table->string('fakultas');
            $table->string('prodi');
            $table->string('foto_path')->nullable();               // path di disk private
            $table->boolean('hardware_ok')->default(false);
            $table->timestamp('jam_mulai')->useCurrent();
            $table->timestamp('jam_selesai')->nullable();          // NULL = sesi masih berjalan
            $table->timestamps();

            $table->index(['computer_id', 'jam_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_sessions');
    }
};
