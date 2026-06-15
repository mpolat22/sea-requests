<?php

namespace App\Support;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferMessage;
use App\Models\OfferMessageRead;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class OrderMessengerService
{
    protected array $supplierProfileUrls = [];

    public function summaries(User $user): array
    {
        $offers = $this->accessibleOffersQuery($user)
            ->with([
                'rfq:id,reference_no,company_name,ship_name,request_type',
                'seller:id,name,company_name',
                'latestMessage.sender:id,name,company_name',
                'messageReads' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->get();

        $items = $offers
            ->map(fn (Offer $offer) => $this->mapSummary($user, $offer))
            ->sortByDesc(fn (array $item) => $item['sort_at'])
            ->values()
            ->map(function (array $item) {
                unset($item['sort_at']);

                return $item;
            });

        return [
            'unread_count' => (int) $items->sum('unread_count'),
            'items' => $items->all(),
        ];
    }

    public function conversation(User $user, Offer $offer): ?array
    {
        if (! $this->canAccess($user, $offer)) {
            return null;
        }

        $record = $this->accessibleOffersQuery($user)
            ->whereKey($offer->id)
            ->with([
                'rfq:id,reference_no,company_name,ship_name,request_type,currency',
                'seller:id,name,company_name',
                'messages.sender:id,name,company_name',
                'messageReads' => fn ($query) => $query->where('user_id', $user->id),
            ])
            ->first();

        if (! $record) {
            return null;
        }

        $readState = $record->messageReads->first();
        $lastReadMessageId = (int) ($readState?->last_read_message_id ?? 0);

        return [
            'offer_id' => $record->id,
            'reference_no' => $record->rfq?->reference_no,
            'request_type' => $record->rfq?->request_type ?: $record->request_type,
            'buyer_company' => $record->rfq?->company_name ?: '-',
            'supplier_name' => $record->seller?->company_name ?: $record->seller?->name ?: '-',
            'counterparty_name' => $this->counterpartyName($user, $record),
            'counterparty_role' => $user->isBuyer() ? 'Supplier' : 'Buyer',
            'counterparty_profile_url' => $this->counterpartyProfileUrl($user, $record),
            'order_url' => $this->orderUrl($user, $record),
            'rfq_url' => $this->rfqUrl($user, $record),
            'ship_name' => $record->rfq?->ship_name ?: '',
            'currency' => $record->currency ?: $record->rfq?->currency ?: 'USD',
            'order_workflow_status' => $this->resolveOrderStatus($record),
            'order_workflow_status_label' => app(OfferOrderWorkflow::class)->label($this->resolveOrderStatus($record)),
            'can_send_messages' => $this->canSendMessages($user, $record),
            'unread_count' => $this->unreadCountForOffer($user, $record, $lastReadMessageId),
            'messages' => $record->messages
                ->map(fn (OfferMessage $message) => $this->mapMessage($user, $message))
                ->values()
                ->all(),
        ];
    }

    public function canAccess(User $user, Offer $offer): bool
    {
        if ($user->isAdmin()) {
            return OfferAward::query()
                ->where('offer_id', $offer->id)
                ->where('status', OfferAward::STATUS_CONFIRMED)
                ->exists();
        }

        if ($user->isBuyer()) {
            return OfferAward::query()
                ->where('offer_id', $offer->id)
                ->where('buyer_id', $user->id)
                ->where('status', OfferAward::STATUS_CONFIRMED)
                ->exists();
        }

        if ($user->isSeller()) {
            return (int) $offer->seller_id === (int) $user->id
                && OfferAward::query()
                    ->where('offer_id', $offer->id)
                    ->where('status', OfferAward::STATUS_CONFIRMED)
                    ->exists();
        }

        return false;
    }

    public function canSendMessages(User $user, Offer $offer): bool
    {
        if (! $this->canAccess($user, $offer)) {
            return false;
        }

        if ($user->isAdmin()) {
            return false;
        }

        return ! $offer->isOrderWorkflowCompleted();
    }

    public function storeMessage(User $user, Offer $offer, string $body, ?UploadedFile $attachment = null): OfferMessage
    {
        $payload = [
            'offer_id' => $offer->id,
            'sender_id' => $user->id,
            'body' => $body !== '' ? $body : null,
        ];

        if ($attachment) {
            $path = $attachment->store("chat/offers/{$offer->id}/messages", 'public');

            $payload = array_merge($payload, [
                'attachment_disk' => 'public',
                'attachment_path' => $path,
                'attachment_name' => $attachment->getClientOriginalName(),
                'attachment_mime_type' => $attachment->getClientMimeType(),
                'attachment_size' => $attachment->getSize(),
            ]);
        }

        $message = OfferMessage::query()->create($payload);

        $this->markRead($user, $offer, $message->id);

        return $message;
    }

    public function markRead(User $user, Offer $offer, ?int $lastReadMessageId = null): void
    {
        $resolvedMessageId = $lastReadMessageId;

        if ($resolvedMessageId === null) {
            $resolvedMessageId = (int) ($offer->messages()->max('id') ?? 0);
        }

        OfferMessageRead::query()->updateOrCreate(
            [
                'offer_id' => $offer->id,
                'user_id' => $user->id,
            ],
            [
                'last_read_message_id' => $resolvedMessageId > 0 ? $resolvedMessageId : null,
                'last_read_at' => now(),
            ]
        );
    }

    private function accessibleOffersQuery(User $user): Builder
    {
        $query = Offer::query()
            ->select([
                'id',
                'rfq_id',
                'seller_id',
                'request_type',
                'currency',
                'order_workflow_status',
                'updated_at',
            ]);

        if ($user->isAdmin()) {
            return $query->whereHas('awards', fn ($awards) => $awards->where('status', OfferAward::STATUS_CONFIRMED));
        }

        if ($user->isBuyer()) {
            return $query->whereHas('awards', fn ($awards) => $awards
                ->where('status', OfferAward::STATUS_CONFIRMED)
                ->where('buyer_id', $user->id));
        }

        if ($user->isSeller()) {
            return $query
                ->where('seller_id', $user->id)
                ->whereHas('awards', fn ($awards) => $awards->where('status', OfferAward::STATUS_CONFIRMED));
        }

        return $query->whereRaw('1 = 0');
    }

    private function mapSummary(User $user, Offer $offer): array
    {
        $latestMessage = $offer->latestMessage;
        $lastReadMessageId = (int) ($offer->messageReads->first()?->last_read_message_id ?? 0);
        $unreadCount = $this->unreadCountForOffer($user, $offer, $lastReadMessageId);
        $sortAt = optional($latestMessage?->created_at ?: $offer->updated_at)?->toISOString() ?: '';

        return [
            'offer_id' => $offer->id,
            'reference_no' => $offer->rfq?->reference_no ?: '-',
            'counterparty_name' => $this->counterpartyName($user, $offer),
            'counterparty_role' => $user->isBuyer() ? 'Supplier' : 'Buyer',
            'counterparty_profile_url' => $this->counterpartyProfileUrl($user, $offer),
            'rfq_url' => $this->rfqUrl($user, $offer),
            'ship_name' => $offer->rfq?->ship_name ?: '',
            'request_type' => $offer->rfq?->request_type ?: $offer->request_type,
            'order_workflow_status' => $this->resolveOrderStatus($offer),
            'order_workflow_status_label' => app(OfferOrderWorkflow::class)->label($this->resolveOrderStatus($offer)),
            'last_message_excerpt' => $this->messageExcerpt($latestMessage),
            'last_message_at' => optional($latestMessage?->created_at ?: $offer->updated_at)?->toISOString(),
            'unread_count' => $unreadCount,
            'can_send_messages' => $this->canSendMessages($user, $offer),
            'order_url' => $this->orderUrl($user, $offer),
            'sort_at' => $sortAt,
        ];
    }

    private function mapMessage(User $user, OfferMessage $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body ?? '',
            'created_at' => optional($message->created_at)?->toISOString(),
            'sender_id' => $message->sender_id,
            'sender_name' => $message->sender?->company_name ?: $message->sender?->name ?: 'User',
            'is_own' => (int) $message->sender_id === (int) $user->id,
            'attachment' => $this->attachment($message),
        ];
    }

    private function attachment(OfferMessage $message): ?array
    {
        if (! $message->attachment_path) {
            return null;
        }

        return [
            'name' => $message->attachment_name,
            'url' => Storage::disk($message->attachment_disk ?: 'public')->url($message->attachment_path),
            'mime_type' => $message->attachment_mime_type,
            'size' => $message->attachment_size,
        ];
    }

    private function counterpartyName(User $user, Offer $offer): string
    {
        if ($user->isBuyer()) {
            return $offer->seller?->company_name ?: $offer->seller?->name ?: 'Supplier';
        }

        return $offer->rfq?->company_name ?: 'Buyer';
    }

    private function unreadCountForOffer(User $user, Offer $offer, int $lastReadMessageId = 0): int
    {
        return OfferMessage::query()
            ->where('offer_id', $offer->id)
            ->where('sender_id', '!=', $user->id)
            ->when($lastReadMessageId > 0, fn ($query) => $query->where('id', '>', $lastReadMessageId))
            ->count();
    }

    private function messageExcerpt(?OfferMessage $message): string
    {
        if (! $message) {
            return 'No messages yet.';
        }

        $body = trim((string) $message->body);

        if ($body !== '') {
            return mb_strimwidth($body, 0, 80, '...');
        }

        return $message->attachment_name
            ? 'Attachment: '.$message->attachment_name
            : 'New message';
    }

    private function resolveOrderStatus(Offer $offer): string
    {
        return app(OfferOrderWorkflow::class)->resolveStatus($offer);
    }

    private function orderUrl(User $user, Offer $offer): ?string
    {
        if ($user->isBuyer()) {
            return route('buyer.orders.show', $offer);
        }

        if ($user->isSeller()) {
            return route('seller.orders.show', $offer);
        }

        return null;
    }

    private function rfqUrl(User $user, Offer $offer): ?string
    {
        if (! $offer->rfq_id) {
            return null;
        }

        if ($user->isBuyer()) {
            return route('buyer.rfqs.show', [
                'rfq' => $offer->rfq_id,
                'offer' => $offer->id,
            ]);
        }

        if ($user->isSeller()) {
            return route('seller.rfqs.show', $offer->rfq_id);
        }

        return null;
    }

    private function counterpartyProfileUrl(User $user, Offer $offer): ?string
    {
        if (! $user->isBuyer()) {
            return null;
        }

        return $this->supplierProfileUrl($offer->seller);
    }

    private function supplierProfileUrl(?User $seller): ?string
    {
        $sellerId = (int) ($seller?->id ?? 0);

        if ($sellerId <= 0) {
            return null;
        }

        if (array_key_exists($sellerId, $this->supplierProfileUrls)) {
            return $this->supplierProfileUrls[$sellerId];
        }

        $listing = SupplierServiceListing::query()
            ->visible()
            ->where('seller_id', $sellerId)
            ->orderBy('category_name')
            ->orderBy('subcategory_name')
            ->first([
                'category_slug',
                'subcategory_slug',
                'vendor_slug',
            ]);

        $this->supplierProfileUrls[$sellerId] = $listing
            ? route('services.show', [
                'category' => $listing->category_slug,
                'subcategory' => $listing->subcategory_slug ?: $listing->category_slug,
                'vendor' => $listing->vendor_slug,
            ])
            : null;

        return $this->supplierProfileUrls[$sellerId];
    }
}
