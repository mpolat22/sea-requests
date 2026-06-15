<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationIndexController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $notifications = $request->user()
            ->notifications()
            ->latest()
            ->paginate(20)
            ->through(function ($notification) {
                $translations = $notification->data['translations']['en']
                    ?? [];

                return [
                    'id' => $notification->id,
                    'title' => $translations['title'] ?? $notification->data['title'] ?? '',
                    'message' => $translations['message'] ?? $notification->data['message'] ?? '',
                    'details' => $translations['details'] ?? $notification->data['details'] ?? [],
                    'action_label' => $translations['action_label'] ?? $notification->data['action_label'] ?? '',
                    'action_url' => $notification->data['action_url'] ?? '',
                    'tone' => $notification->data['tone'] ?? 'info',
                    'read_at' => optional($notification->read_at)?->toIso8601String(),
                    'created_at' => optional($notification->created_at)?->toIso8601String(),
                ];
            });

        return Inertia::render('Notifications/Index', [
            'notificationsPage' => $notifications,
        ]);
    }
}
