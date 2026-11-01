<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ExaminationNote;
use Illuminate\Http\Request;

/**
 * Catatan Pemeriksaan milik Dokter — BERDIRI SENDIRI tanpa sesi EKG.
 * Dokter cukup memilih pasien dalam kewenangannya lalu menulis catatan;
 * catatan langsung tampil di akun pasien yang bersangkutan.
 */
class NoteController extends Controller
{
    public function index(Request $request)
    {
        $doctor = MyPatientController::currentDoctor($request);

        $notes = ExaminationNote::query()
            ->with(['patient', 'doctor'])
            ->when($doctor, fn ($q) => $q->where('doctor_id', $doctor->id), fn ($q) => $q->whereRaw('1 = 0'))
            ->latest('created_at')
            ->paginate(15);

        // Pasien dalam kewenangan dokter, untuk dropdown pilih pasien.
        $patients = MyPatientController::patientsQuery($request->user()->id)
            ->orderBy('name')
            ->get();

        return view('doctor.notes.index', compact('notes', 'patients'));
    }

    public function store(Request $request)
    {
        $doctor = MyPatientController::currentDoctor($request);

        abort_unless($doctor !== null, 403, 'Profil dokter belum ditautkan ke akun Anda.');

        $data = $request->validate([
            'patient_id' => ['required', 'integer'],
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'patient_id.required' => 'Pasien wajib dipilih.',
            'body.required' => 'Isi catatan wajib diisi.',
        ]);

        // Catatan hanya boleh untuk pasien dalam kewenangan dokter.
        $patient = MyPatientController::patientsQuery($request->user()->id)
            ->findOrFail($data['patient_id']);

        $note = ExaminationNote::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'body' => $data['body'],
        ]);

        AuditLog::record('tambah_catatan_pemeriksaan', 'examination_note', $note->id, [
            'patient_id' => $patient->id,
        ]);

        return redirect()->route('doctor.notes.index')->with('success', 'Catatan pemeriksaan tersimpan.');
    }

    public function edit(Request $request, ExaminationNote $note)
    {
        abort_unless($note->doctor_id === MyPatientController::currentDoctor($request)?->id, 403);

        return view('doctor.notes.edit', ['note' => $note->load(['patient', 'doctor'])]);
    }

    public function update(Request $request, ExaminationNote $note)
    {
        abort_unless($note->doctor_id === MyPatientController::currentDoctor($request)?->id, 403);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ], [
            'body.required' => 'Isi catatan wajib diisi.',
        ]);

        $note->update($data);

        AuditLog::record('ubah_catatan_pemeriksaan', 'examination_note', $note->id);

        return redirect()->route('doctor.notes.index')->with('success', 'Catatan diperbarui (perubahan tercatat pada audit log).');
    }
}
