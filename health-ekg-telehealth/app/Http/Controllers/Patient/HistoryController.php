<?php

namespace App\Http\Controllers\Patient;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $patient = $request->user()->patient;

        $sessions = $patient
            ? $patient->sessions()->with(['device', 'doctor'])
                ->when($request->filled('from'), fn ($q) => $q->whereDate('started_at', '>=', $request->input('from')))
                ->when($request->filled('to'), fn ($q) => $q->whereDate('started_at', '<=', $request->input('to')))
                ->latest('started_at')
                ->paginate(15)
                ->withQueryString()
            : collect();

        return view('patient.history.index', [
            'patient' => $patient,
            'sessions' => $sessions,
        ]);
    }
}
