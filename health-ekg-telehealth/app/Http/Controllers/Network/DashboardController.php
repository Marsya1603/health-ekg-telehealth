<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\SystemEvent;
use App\Services\QosService;
use App\Services\StatusService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $range = (int) ($request->input('range', 1440)); // menit, default 24 jam
        $range = max(60, min(10080, $range));

        $from = CarbonImmutable::now()->subMinutes($range);
        $to = CarbonImmutable::now();

        $infra = StatusService::all();

        $timeout = (int) config('healthekg.device.heartbeat_timeout', 300);
        $deviceStats = [
            'total' => Device::count(),
            'active' => Device::where('last_message_at', '>=', now()->subSeconds($timeout))->count(),
            'registered' => Device::where('is_registered', true)->count(),
        ];

        $qos = QosService::summary($from, $to);
        $series = QosService::timeSeries($from, $to, null, $range <= 360 ? 5 : 15);

        $events = SystemEvent::latest('occurred_at')->limit(10)->get();

        return view('network.dashboard', [
            'infra' => $infra,
            'deviceStats' => $deviceStats,
            'qos' => $qos,
            'series' => $series,
            'events' => $events,
            'rangeMinutes' => $range,
            'from' => $from,
            'to' => $to,
        ]);
    }
}
