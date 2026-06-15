<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

class NotificationController extends Controller
{
    public function preview(Request $request): JsonResponse
    {
        $items = $request->user()?->notifications()
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (DatabaseNotification $notification) => $this->serializeNotification($notification))
            ->values()
            ->all() ?? [];

        return response()->json([
            'items' => $items,
        ]);
    }

    public function read(Request $request, DatabaseNotification $notification): RedirectResponse
    {
        abort_unless(
            $request->user()
            && $notification->notifiable_type === $request->user()::class
            && (int) $notification->notifiable_id === (int) $request->user()->id,
            403
        );

        if ($notification->read_at === null) {
            $notification->markAsRead();
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()?->unreadNotifications()->update([
            'read_at' => now(),
        ]);

        return back();
    }

    private function serializeNotification(DatabaseNotification $notification): array
    {
        $translations = $notification->data['translations']['en'] ?? [];

        return [
            'id' => $notification->id,
            'title' => $translations['title'] ?? $notification->data['title'] ?? '',
            'message' => $translations['message'] ?? $notification->data['message'] ?? '',
            'action_label' => $translations['action_label'] ?? $notification->data['action_label'] ?? '',
            'action_url' => $notification->data['action_url'] ?? '',
            'tone' => $notification->data['tone'] ?? 'info',
            'read_at' => optional($notification->read_at)?->toIso8601String(),
            'created_at' => optional($notification->created_at)?->toIso8601String(),
        ];
    }
}
