<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekg_packets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ekg_session_id')->constrained('ekg_sessions')->cascadeOnDelete();
            $table->foreignId('device_id')->constrained('devices')->cascadeOnDelete();
            $table->string('device_uid', 64)->index();
            $table->unsignedBigInteger('packet_number');
            $table->unsignedInteger('sample_count');
            $table->unsignedInteger('sampling_rate')->nullable();
            $table->dateTime('device_time', 3)->nullable();   // Timestamp perangkat (dari frame)
            $table->dateTime('received_at', 3);               // Waktu diterima worker
            $table->unsignedInteger('payload_size')->nullable();    // Ukuran plaintext sampel (byte)
            $table->unsignedInteger('encrypted_size')->nullable();  // Ukuran ciphertext+tag (byte)
            $table->decimal('processing_ms', 10, 2)->nullable();    // Durasi proses worker
            $table->char('nonce', 32)->nullable();                  // Nonce hex, untuk deteksi pemakaian ulang
            $table->timestamps();

            $table->unique(['ekg_session_id', 'packet_number']);
            $table->unique(['device_uid', 'nonce']);
            $table->index(['received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekg_packets');
    }
};
