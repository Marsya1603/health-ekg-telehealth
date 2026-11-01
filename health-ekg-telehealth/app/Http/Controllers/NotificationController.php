<?php

namespace App\Http\Controllers;

use App\Models\AppNotification;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->appNotifications()
            ->latest('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('notifications.index', [
            'notifications' => $notifications,
        ]);
    }

    public function read(Request $request, int $id)
    {
        $notification = $request->user()->appNotifications()->findOrFail($id);
        $notification->update(['is_read' => true]);

        if ($notification->link) {
            return redirect($notification->link);
        }

        return back();
    }

    public function readAll(Request $request)
    {
        $request->user()->appNotifications()->unread()->update(['is_read' => true]);

        return back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }
}
