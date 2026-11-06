<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// Notification centre (top bar). In-app only; no email/push is sent.
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $q = auth()->user()->notifications();
        if ($request->query('show') === 'unread') {
            $q->whereNull('read_at');
        }
        return view('admin.notifications', ['items' => $q->paginate(config('csa.per_page'))->withQueryString()]);
    }

    public function read(string $id)
    {
        $n = auth()->user()->notifications()->whereKey($id)->firstOrFail(); // only the owner's own notifications
        $n->markAsRead();
        $link = $n->data['link'] ?? null;
        // Only follow app-relative links
        return $link && str_starts_with($link, '/') && ! str_starts_with($link, '//') ? redirect($link) : back();
    }

    public function readAll()
    {
        $n = auth()->user()->unreadNotifications()->update(['read_at' => now()]);
        return back()->with('ok', "{$n} notification(s) marked read.");
    }
}
