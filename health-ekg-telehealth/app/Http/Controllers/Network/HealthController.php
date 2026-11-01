<?php

namespace App\Http\Controllers\Network;

use App\Http\Controllers\Controller;
use App\Models\SystemEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class HealthController extends Controller
{
    public function server()
    {
        // Metrik server diambil dari snapshot yang dikumpulkan oleh command
        // app:collect-server-health (scheduler). Bila scheduler belum berjalan,
        // dashboard menampilkan keadaan kosong yang jujur, bukan angka statis.
        $snapshot = Cache::get('server-health');

        return view('network.server-health', ['snapshot' => $snapshot]);
    }

    public function events(Request $request)
    {
        $events = SystemEvent::query()
            ->when($request->filled('component'), fn ($q) => $q->where('component', $request->input('component')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->latest('occurred_at')
            ->paginate(25)
            ->withQueryString();

        return view('network.events', ['events' => $events]);
    }
}
