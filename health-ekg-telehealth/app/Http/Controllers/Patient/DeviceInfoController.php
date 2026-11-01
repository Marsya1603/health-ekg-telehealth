<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class DeviceInfoController extends Controller
{
    public function show(Request $request)
    {
        $patient = $request->user()->patient;

        $device = $patient?->devices()->orderBy('id')->first();
        [$statusKey, $statusText] = $device?->dataStatus() ?? ['unknown', 'Belum ada perangkat terdaftar'];

        // Halaman ini TIDAK menampilkan kredensial MQTT, secret key ASCON-128,
        // atau konfigurasi server sensitif. Hanya informasi yang diizinkan pasien.
        return view('patient.device.show', [
            'patient' => $patient,
            'device' => $device,
            'statusKey' => $statusKey,
            'statusText' => $statusText,
        ]);
    }
}
