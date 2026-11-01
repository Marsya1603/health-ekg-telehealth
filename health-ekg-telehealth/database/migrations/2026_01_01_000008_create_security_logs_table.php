<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_logs', function (Blueprint $table) {
            $table->id();
            $table->string('device_uid', 64)->index();
            $table->foreignId('ekg_session_id')->nullable()->constrained('ekg_sessions')->nullOnDelete();
            $table->unsignedBigInteger('packet_number')->nullable();
            // dekripsi_ok | tag_invalid | format_invalid | dekripsi_gagal | nonce_error | perangkat_tak_dikenal
            $table->string('event_type', 32)->index();
            $table->string('error_code', 32)->nullable(); // Kode aman, tanpa detail rahasia
            $table->decimal('processing_ms', 10, 2)->nullable();     // Waktu dekripsi (ms)
            $table->unsignedInteger('payload_plain_size')->nullable();  // Ukuran plaintext (byte)
            $table->unsignedInteger('payload_cipher_size')->nullable(); // Ukuran ciphertext+tag (byte)
            $table->dateTime('occurred_at', 3)->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_logs');
    }
};
