<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Support\OrderMessengerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\File;

class OrderMessengerController extends Controller
{
    public function index(Request $request, OrderMessengerService $messenger): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 403);

        return response()->json($messenger->summaries($user));
    }

    public function show(Request $request, Offer $offer, OrderMessengerService $messenger): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 403);
        abort_unless($messenger->canAccess($user, $offer), 404);

        $conversation = $messenger->conversation($user, $offer);

        abort_unless($conversation, 404);

        return response()->json([
            'conversation' => $conversation,
        ]);
    }

    public function store(Request $request, Offer $offer, OrderMessengerService $messenger): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 403);
        abort_unless($messenger->canAccess($user, $offer), 404);
        abort_unless($messenger->canSendMessages($user, $offer), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:4000'],
            'attachment' => [
                'nullable',
                File::types(['pdf', 'jpg', 'jpeg', 'png', 'webp'])->max(2048),
            ],
        ]);

        $body = trim((string) ($validated['body'] ?? ''));
        $attachment = $request->file('attachment');

        if ($body === '' && ! $attachment) {
            return response()->json([
                'message' => 'Message text or attachment is required.',
                'errors' => [
                    'body' => ['Message text or attachment is required.'],
                ],
            ], 422);
        }

        $messenger->storeMessage($user, $offer, $body, $attachment);

        $conversation = $messenger->conversation($user, $offer);

        return response()->json([
            'conversation' => $conversation,
        ], 201);
    }

    public function markRead(Request $request, Offer $offer, OrderMessengerService $messenger): JsonResponse
    {
        $user = $request->user();

        abort_unless($user, 403);
        abort_unless($messenger->canAccess($user, $offer), 404);

        $messenger->markRead($user, $offer);

        return response()->json($messenger->summaries($user));
    }
}
