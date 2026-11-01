<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\EkgSession;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(Request $request)
    {
        $patientIds = MyPatientController::patientsQuery($request->user()->id)->pluck('id');

        $sessions = EkgSession::query()
            ->with(['patient', 'device'])
            ->whereIn('patient_id', $patientIds)
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w
                    ->where('session_code', 'like', $term)
                    ->orWhereHas('patient', fn ($p) => $p->where('name', 'like', $term)));
            })
            ->when($request->filled('from'), fn ($q) => $q->whereDate('started_at', '>=', $request->input('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('started_at', '<=', $request->input('to')))
            ->latest('started_at')
            ->paginate(15)
            ->withQueryString();

        return view('doctor.history.index', compact('sessions'));
    }
}
