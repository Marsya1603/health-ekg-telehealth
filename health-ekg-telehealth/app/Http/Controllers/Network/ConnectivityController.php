<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SystemEvent;
use App\Models\TransmissionLog;
use Illuminate\Http\Request;

class ConnectivityController extends Controller
{
    public function devices(Request $request)
    {
        $timeout = (int) config('healthekg.device.heartbeat_timeout', 300);

        $devices = Device::query()
            ->with('patient:id,code,name')
            ->when($request->filled('q'), fn ($q) => $q->where('device_uid', 'like', '%'.$request->string('q').'%'))
            ->when($request->filled('state'), function ($q) use ($request, $timeout) {
                $s = $request->input('state');
                if ($s === 'aktif') {
                    $q->where('last_message_at', '>=', now()->subSeconds($timeout));
                } elseif ($s === 'idle') {
                    $q->whereNotNull('last_message_at')->where('last_message_at', '<', now()->subSeconds($timeout));
                } elseif ($s === 'unknown') {
                    $q->whereNull('last_message_at');
                }
            })
            ->orderByDesc('last_message_at')
            ->paginate(15)
            ->withQueryString();

        $events = SystemEvent::where('component', 'device')
            ->latest('occurred_at')
            ->limit(15)
            ->get();

        return view('network.devices', compact('devices', 'events', 'timeout'));
    }

    public function mqtt(Request $request)
    {
        // Statistik pesan dihitung dari log transmisi aktual (bukan angka statis).
        $stats24h = TransmissionLog::where('received_at', '>=', now()->subDay())
            ->selectRaw("status, count(*) as total")
            ->groupBy('status')
            ->pluck('total', 'status');

        $statsByValidation = TransmissionLog::where('received_at', '>=', now()->subDay())
            ->selectRaw("validation_status, count(*) as total")
            ->whereNotNull('validation_status')
            ->groupBy('validation_status')
            ->pluck('total', 'validation_status');

        $lastMessage = TransmissionLog::latest('received_at')->first();

        $reconnects = SystemEvent::where('component', 'worker')
            ->whereIn('event_type', ['broker_reconnect', 'broker_connect', 'broker_disconnect'])
            ->latest('occurred_at')
            ->limit(10)
            ->get();

        $logs = TransmissionLog::query()
            ->when($request->filled('device'), fn ($q) => $q->where('device_uid', $request->input('device')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('received_at')
            ->paginate(20)
            ->withQueryString();

        return view('network.mqtt', [
            'stats24h' => $stats24h,
            'statsByValidation' => $statsByValidation,
            'lastMessage' => $lastMessage,
            'reconnects' => $reconnects,
            'logs' => $logs,
            'topic' => config('healthekg.mqtt.topic_prefix').'/telemetry/+', // hanya pola topic, tanpa kredensial
            'qos' => config('healthekg.mqtt.qos'),
            'workerTimeout' => config('healthekg.worker_timeout'),
        ]);
    }
}
