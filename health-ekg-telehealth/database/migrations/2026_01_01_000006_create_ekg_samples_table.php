<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ekg_samples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ekg_session_id')->constrained('ekg_sessions')->cascadeOnDelete();
            $table->foreignId('ekg_packet_id')->constrained('ekg_packets')->cascadeOnDelete();
            $table->unsignedInteger('seq');            // Urutan sampel dalam paket
            $table->decimal('value_mv', 10, 3);        // Nilai EKG dalam milivolt
            $table->dateTime('sampled_at', 3);         // Waktu sampling (perangkat, ketelitian ms)
            $table->timestamp('created_at')->nullable();

            $table->index(['ekg_session_id', 'sampled_at']);
            $table->index(['sampled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ekg_samples');
    }
};
