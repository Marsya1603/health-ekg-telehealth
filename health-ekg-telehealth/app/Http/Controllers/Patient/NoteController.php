<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use App\Models\ExaminationNote;
use Illuminate\Http\Request;

/**
 * Catatan Dokter untuk Pasien — hanya catatan milik pasien sendiri
 * (difilter langsung via patient_id, anti manipulasi URL).
 * Read-only: pasien tidak dapat mengubah catatan klinis.
 */
class NoteController extends Controller
{
    public function index(Request $request)
    {
        $patient = $request->user()->patient;

        $notes = $patient
            ? ExaminationNote::query()
                ->where('patient_id', $patient->id)
                ->with(['doctor'])
                ->latest('created_at')
                ->paginate(15)
            : collect();

        return view('patient.notes.index', [
            'patient' => $patient,
            'notes' => $notes,
        ]);
    }
}
