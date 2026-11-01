<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExaminationNote;
use Illuminate\Http\Request;

/**
 * Catatan pemeriksaan untuk Admin Puskesmas — HANYA BACA (read-only).
 * Pembuatan dan pengubahan catatan adalah kewenangan dokter;
 * admin cukup mengawasi tanpa bisa mengubah isi klinis.
 */
class NoteController extends Controller
{
    public function index(Request $request)
    {
        $notes = ExaminationNote::query()
            ->with(['patient', 'doctor'])
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where('body', 'like', $term)
                    ->orWhereHas('patient', fn ($p) => $p
                        ->where('name', 'like', $term)
                        ->orWhere('code', 'like', $term));
            })
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.notes.index', compact('notes'));
    }

    public function show(ExaminationNote $note)
    {
        $note->load(['patient', 'doctor']);

        return view('admin.notes.show', compact('note'));
    }
}
