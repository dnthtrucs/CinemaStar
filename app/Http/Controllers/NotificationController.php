<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()->latest()->paginate(15);

        return view('notifications.index', compact('notifications'));
    }

    public function feed(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'items' => $user->unreadNotifications()->latest()->limit(5)->get()->map(fn ($item) => [
                'id' => $item->id,
                'title' => $item->data['title'] ?? 'Bạn có thông báo CinemaStar mới',
            ]),
        ])->header('Cache-Control', 'no-store, private');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('notifications.index')->with('success', 'Đã đánh dấu tất cả thông báo là đã đọc.');
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return redirect()->route('notifications.index');
    }
}
