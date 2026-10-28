<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;

/** The bell in the navbar (JSON) and the full notification list page. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $notifications = $request->user()->notifications()
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->paginate(20)
            ->withQueryString()
            ->through(fn (DatabaseNotification $n) => $this->present($n));

        return Inertia::render('Notifications/Index', [
            'notifications' => $notifications,
            'filters' => ['unread' => $request->boolean('unread')],
        ]);
    }

    /** Latest items + unread count for the dropdown (also polled for the badge). */
    public function recent(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'unread_count' => $user->unreadNotifications()->count(),
            'items' => $user->notifications()->limit(8)->get()->map(fn (DatabaseNotification $n) => $this->present($n)),
        ]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $request->user()->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return response()->json(['unread_count' => $request->user()->unreadNotifications()->count()]);
    }

    public function markAllRead(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return $request->expectsJson()
            ? response()->json(['unread_count' => 0])
            : back()->with('success', 'Semua notifikasi ditandai sudah dibaca.');
    }

    /** @return array<string, mixed> */
    private function present(DatabaseNotification $notification): array
    {
        return [
            'id' => $notification->id,
            'kind' => $notification->data['kind'] ?? 'info',
            'title' => $notification->data['title'] ?? '',
            'body' => $notification->data['body'] ?? null,
            'url' => $notification->data['url'] ?? null,
            'actor_name' => $notification->data['actor_name'] ?? null,
            'read' => $notification->read_at !== null,
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
