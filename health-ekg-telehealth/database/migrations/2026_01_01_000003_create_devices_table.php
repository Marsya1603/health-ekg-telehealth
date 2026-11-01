<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            // Device ID unik yang dipakai di frame MQTT (mis. DEV-XXXX). Digunakan worker.
            $table->string('device_uid', 64)->unique();
            $table->string('name');
            $table->string('type', 64)->nullable(); // mis. ESP32 + Olimex SHIELD-EKG-EMG
            $table->foreignId('patient_id')->nullable()->constrained('patients')->nullOnDelete();
            $table->string('firmware_version', 64)->nullable();
            $table->boolean('is_registered')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('last_message_at')->nullable(); // Pesan terakhir yang diproses worker
            $table->unsignedBigInteger('last_packet_number')->nullable();
            $table->timestamps();

            $table->index(['patient_id']);
            $table->index(['last_message_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices');
    }
};
