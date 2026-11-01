<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transmission_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_uid', 64)->index();
            $table->foreignId('ekg_session_id')->nullable()->constrained('ekg_sessions')->nullOnDelete();
            $table->unsignedBigInteger('packet_number')->nullable();
            $table->string('message_type', 16)->default('ecg'); // ecg | session_end
            $table->string('topic', 190)->nullable();
            $table->unsignedInteger('frame_size')->nullable();       // Ukuran frame total yang diterima
            $table->unsignedInteger('payload_size')->nullable();     // Ukuran plaintext sampel
            $table->unsignedInteger('encrypted_size')->nullable();   // Ukuran ciphertext+tag
            $table->enum('status', ['diterima', 'valid', 'invalid', 'duplikat', 'gagal'])->default('diterima');
            $table->string('validation_status', 32)->nullable();     // format_ok, tag_invalid, dst.
            $table->decimal('delay_ms', 12, 2)->nullable();          // device_time vs received_at (one-way)
            $table->decimal('processing_ms', 10, 2)->nullable();     // Durasi proses worker
            $table->string('error_code', 32)->nullable();            // Kode error aman untuk ditampilkan
            $table->string('source', 32)->default('worker');         // Komponen sumber log
            $table->dateTime('received_at', 3)->index();             // Waktu diterima (ketelitian ms)
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transmission_logs');
    }
};
