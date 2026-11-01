<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabel metrik jaringan untuk hasil pengukuran/pengujian terdokumentasi
        // (mis. hasil sesi uji QoS). Agregasi harian dashboard dihitung dari
        // transmission_logs; tabel ini menyimpan hasil pengukuran eksplisit.
        Schema::create('network_metrics', function (Blueprint $table) {
            $table->id();
            $table->string('device_uid', 64)->nullable()->index();
            $table->foreignId('ekg_session_id')->nullable()->constrained('ekg_sessions')->nullOnDelete();
            $table->string('metric', 32)->index(); // delay | throughput | packet_loss | payload_size | encryption_time | decryption_time
            $table->decimal('value', 16, 4);
            $table->string('unit', 16);            // ms | kbps | % | byte
            $table->unsignedInteger('samples')->nullable();
            $table->timestamp('period_start')->nullable();
            $table->timestamp('period_end')->nullable();
            $table->string('method', 64)->nullable();  // Metode pengukuran yang terdokumentasi
            $table->string('source', 32)->nullable();  // worker | manual | wireshark
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at')->useCurrent()->index();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('network_metrics');
    }
};
