<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catatan pemeriksaan kini BERDIRI SENDIRI — tidak lagi wajib menautkan
     * sesi EKG. Dokter cukup memilih pasien lalu menulis catatan.
     *
     * - Tambah kolom patient_id (tautan langsung ke pasien).
     * - Catatan lama: patient_id diisi otomatis dari pasien pada sesi terkait,
     *   sehingga tidak ada data yang hilang.
     * - Lepas kolom ekg_session_id.
     *
     * Aman untuk instalasi lama maupun baru: dilewati otomatis bila struktur
     * lama (kolom ekg_session_id) sudah tidak ada.
     */
    public function up(): void
    {
        if (! Schema::hasTable('examination_notes')
            || ! Schema::hasColumn('examination_notes', 'ekg_session_id')) {
            return; // Instalasi baru / struktur sudah dikonversi.
        }

        Schema::table('examination_notes', function (Blueprint $table) {
            $table->foreignId('patient_id')->nullable()->after('id')
                ->constrained('patients')->nullOnDelete();
        });

        // Backfill: ambil patient_id dari sesi yang terkait pada catatan lama.
        DB::table('examination_notes')
            ->whereNull('patient_id')
            ->orderBy('id')
            ->chunkById(200, function ($notes): void {
                foreach ($notes as $note) {
                    $patientId = DB::table('ekg_sessions')
                        ->where('id', $note->ekg_session_id)
                        ->value('patient_id');

                    if ($patientId !== null) {
                        DB::table('examination_notes')
                            ->where('id', $note->id)
                            ->update(['patient_id' => $patientId]);
                    }
                }
            });

        Schema::table('examination_notes', function (Blueprint $table) {
            $table->dropForeign(['ekg_session_id']);
            $table->dropColumn('ekg_session_id');
        });
    }

    public function down(): void
    {
        // Konversi ini tidak dibalikkan: aplikasi sudah tidak memakai struktur lama.
    }
};
