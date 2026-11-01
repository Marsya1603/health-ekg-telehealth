<?php

namespace App\Console\Commands;

use App\Models\AppNotification;
use App\Models\EkgSession;
use App\Models\SystemEvent;
use Illuminate\Console\Command;

/**
 * Memeriksa sesi EKG yang berstatus aktif tetapi berhenti mengirim data
 * melebihi ambang, lalu membuat notifikasi berbasis event nyata (bukan statis).
 */
class CheckDataGapsCommand extends Command
{
    protected $signature = 'app:check-data-gaps';

    protected $description = 'Mendeteksi sesi EKG aktif yang berhenti mengirim data dan membuat notifikasi';

    public function handle(): int
    {
        $gapMinutes = (int) config('healthekg.device.gap_alert_minutes', 10);
        $threshold = now()->subMinutes($gapMinutes);

        // Sesi aktif yang: sudah dimulai sebelum ambang, dan paket terakhirnya
        // (atau waktu mulai bila belum ada paket) lebih tua dari ambang.
        $sessions = EkgSession::query()
            ->with(['patient.user', 'doctor.user', 'device'])
            ->where('status', 'aktif')
            ->where('gap_notified_at', null)
            ->where('started_at', '<', $threshold)
            ->get()
            ->filter(function (EkgSession $session) use ($threshold) {
                $last = $session->packets()->max('received_at');

                $lastAt = $last ? \Carbon\Carbon::parse($last) : $session->started_at;

                return $lastAt->lt($threshold);
            });

        if ($sessions->isEmpty()) {
            $this->info('Tidak ada sesi dengan jeda data.');

            return self::SUCCESS;
        }

        foreach ($sessions as $session) {
            $patientName = $session->patient?->name ?? '-';
            $title = 'Data EKG tidak diterima';
            $body = "Sesi {$session->session_code} milik pasien {$patientName} tidak menerima data baru lebih dari {$gapMinutes} menit.";

            // Notifikasi ke dokter penanggung jawab (bila memiliki akun).
            if ($session->doctor?->user_id) {
                AppNotification::create([
                    'user_id' => $session->doctor->user_id,
                    'category' => 'data',
                    'title' => $title,
                    'body' => $body,
                    'link' => route('doctor.monitoring.show', $session),
                    'created_at' => now(),
                ]);
            }

            // Notifikasi ke pasien (bila memiliki akun).
            if ($session->patient?->user_id) {
                AppNotification::create([
                    'user_id' => $session->patient->user_id,
                    'category' => 'data',
                    'title' => 'Pemantauan sedang menunggu data',
                    'body' => 'Perangkat pemantauan Anda belum mengirim data baru dalam '.$gapMinutes.' menit terakhir.',
                    'link' => route('patient.ecg.show', $session),
                    'created_at' => now(),
                ]);
            }

            // Notifikasi ke semua admin.
            $admins = \App\Models\User::where('role', 'admin')->where('is_active', true)->get();
            foreach ($admins as $admin) {
                AppNotification::create([
                    'user_id' => $admin->id,
                    'category' => 'data',
                    'title' => $title,
                    'body' => $body,
                    'link' => route('admin.patients.show', $session->patient_id),
                    'created_at' => now(),
                ]);
            }

            SystemEvent::log('backend', 'jeda_data_ekg', 'peringatan', null,
                'Sesi '.$session->session_code.' tidak menerima data > '.$gapMinutes.' menit');

            $session->forceFill(['gap_notified_at' => now()])->save();

            $this->warn('Notifikasi jeda data dibuat untuk sesi '.$session->session_code);
        }

        return self::SUCCESS;
    }
}
