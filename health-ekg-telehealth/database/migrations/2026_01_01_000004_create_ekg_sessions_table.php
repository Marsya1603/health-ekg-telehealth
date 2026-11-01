<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekg_sessions', function (Blueprint $table) {
            $table->id();
            // Session ID dari perangkat (UUID). Dibuat oleh ESP32, divalidasi worker.
            $table->string('session_code', 64)->unique();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->nullOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->unsignedInteger('sampling_rate')->nullable(); // Hz, dari metadata frame aktual
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->unsignedBigInteger('sample_count')->default(0);
            $table->unsignedBigInteger('packet_count')->default(0);
            $table->enum('status', ['aktif', 'selesai', 'dihentikan', 'gagal'])->default('aktif');
            $table->timestamp('gap_notified_at')->nullable(); // Penanda notifikasi jeda data terkirim
            $table->timestamps();

            $table->index(['patient_id', 'started_at']);
            $table->index(['status']);
            $table->index(['started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekg_sessions');
    }
};
