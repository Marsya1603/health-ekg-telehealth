<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Patient;
use App\Models\SystemEvent;
use App\Models\TransmissionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class DeviceController extends Controller
{
    public function index(Request $request)
    {
        $timeout = (int) config('healthekg.device.heartbeat_timeout', 300);

        $devices = Device::query()
            ->with('patient')
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = '%'.$request->string('q').'%';
                $q->where(fn ($w) => $w->where('name', 'like', $term)
                    ->orWhere('device_uid', 'like', $term));
            })
            ->when($request->filled('status'), function ($q) use ($request, $timeout) {
                $s = $request->input('status');
                if ($s === 'aktif') {
                    $q->where('last_message_at', '>=', now()->subSeconds($timeout));
                } elseif ($s === 'idle') {
                    $q->whereNotNull('last_message_at')->where('last_message_at', '<', now()->subSeconds($timeout));
                } elseif ($s === 'unknown') {
                    $q->whereNull('last_message_at');
                }
            })
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $patients = Patient::orderBy('name')->get();

        return view('admin.devices.index', compact('devices', 'patients', 'timeout'));
    }

    public function create()
    {
        $patients = Patient::where('is_active', true)->orderBy('name')->get();

        return view('admin.devices.form', [
            'device' => null,
            'patients' => $patients,
            'suggestedUid' => 'DEV-'.Str::upper(Str::random(8)),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $device = Device::create($data);
        AuditLog::record('registrasi_perangkat', 'device', $device->id, ['device_uid' => $device->device_uid]);

        return redirect()->route('admin.devices.show', $device)
            ->with('success', 'Perangkat '.$device->device_uid.' berhasil diregistrasikan.');
    }

    public function show(Device $device)
    {
        $device->load('patient');
        [$statusKey, $statusText] = $device->dataStatus();

        $sessions = $device->sessions()->with('patient')->latest('started_at')->limit(10)->get();

        $logs = $device->transmissionLogs()
            ->latest('received_at')
            ->limit(15)
            ->get(['device_uid', 'packet_number', 'message_type', 'status', 'error_code', 'received_at']);

        $events = SystemEvent::where('component', 'device')
            ->where('message', 'like', '%'.$device->device_uid.'%')
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        return view('admin.devices.show', compact('device', 'statusKey', 'statusText', 'sessions', 'logs', 'events'));
    }

    public function edit(Device $device)
    {
        $patients = Patient::orderBy('name')->get();

        return view('admin.devices.form', ['device' => $device, 'patients' => $patients, 'suggestedUid' => null]);
    }

    public function update(Request $request, Device $device)
    {
        $data = $this->validated($request, $device->id);
        $device->update($data);

        AuditLog::record('ubah_perangkat', 'device', $device->id, ['device_uid' => $device->device_uid]);

        return redirect()->route('admin.devices.show', $device)
            ->with('success', 'Data perangkat berhasil diperbarui.');
    }

    public function toggle(Device $device)
    {
        $device->update(['is_registered' => ! $device->is_registered]);

        AuditLog::record('ubah_status_perangkat', 'device', $device->id, [
            'device_uid' => $device->device_uid,
            'is_registered' => $device->is_registered,
        ]);

        if (! $device->is_registered) {
            SystemEvent::log('device', 'registrasi_dinonaktifkan', 'peringatan', null,
                'Perangkat '.$device->device_uid.' dinonaktifkan dari dashboard oleh admin');
        }

        return back()->with('success', 'Status registrasi perangkat diperbarui: '.($device->is_registered ? 'Terdaftar' : 'Nonaktif').'.');
    }

    private function validated(Request $request, ?int $ignoreId = null): array
    {
        $uidRule = 'unique:devices,device_uid';
        if ($ignoreId) {
            $uidRule .= ','.$ignoreId;
        }

        return $request->validate([
            'device_uid' => ['required', 'string', 'max:64', 'regex:/^[A-Za-z0-9_-]{4,64}$/', $uidRule],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['nullable', 'string', 'max:64'],
            'patient_id' => ['nullable', 'exists:patients,id'],
            'firmware_version' => ['nullable', 'string', 'max:64'],
            'is_registered' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], [
            'device_uid.required' => 'Device ID wajib diisi.',
            'device_uid.unique' => 'Device ID sudah terdaftar.',
            'device_uid.regex' => 'Device ID hanya boleh huruf, angka, tanda hubung, dan garis bawah (4-64 karakter).',
            'name.required' => 'Nama perangkat wajib diisi.',
        ]);
    }
}
