<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('lemburs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->date('tanggal_kegiatan');
            $table->string('nama_kegiatan');
            $table->string('lokasi_kegiatan');

            $table->string('foto_kegiatan')->nullable();
            $table->dateTime('foto_kegiatan_at')->nullable();

            $table->string('foto_pulang')->nullable();
            $table->dateTime('foto_pulang_at')->nullable();

            $table->time('waktu_pulang')->nullable();

            $table->string('status')->default('draft');

            $table->dateTime('locked_at')->nullable();

            $table->foreignId('locked_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            // 1 pegawai hanya boleh memiliki 1 lembur dalam 1 hari
            $table->unique(['user_id', 'tanggal_kegiatan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lemburs');
    }
};
