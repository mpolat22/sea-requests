<?php

namespace Tests\Feature;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Models\OfferMessage;
use App\Models\Rfq;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OrderMessengerWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_buyer_and_seller_can_exchange_messages_for_a_confirmed_order(): void
    {
        [$buyer, $seller, $offer] = $this->createAwardedOrder();

        $this->actingAs($buyer)
            ->getJson(route('messenger.conversations.index'))
            ->assertOk()
            ->assertJsonPath('unread_count', 0)
            ->assertJsonPath('items.0.offer_id', $offer->id)
            ->assertJsonPath('items.0.counterparty_name', 'Atlas Marine')
            ->assertJsonPath('items.0.order_url', route('buyer.orders.show', $offer))
            ->assertJsonPath('items.0.rfq_url', route('buyer.rfqs.show', [
                'rfq' => $offer->rfq_id,
                'offer' => $offer->id,
            ]));

        $this->actingAs($buyer)
            ->post(route('messenger.conversations.messages.store', $offer), [
                'body' => 'Please confirm the revised delivery arrangement for this order.',
            ])
            ->assertCreated()
            ->assertJsonPath('conversation.offer_id', $offer->id)
            ->assertJsonPath('conversation.messages.0.body', 'Please confirm the revised delivery arrangement for this order.');

        $this->actingAs($seller)
            ->getJson(route('messenger.conversations.show', $offer))
            ->assertOk()
            ->assertJsonPath('conversation.unread_count', 1)
            ->assertJsonPath('conversation.messages.0.body', 'Please confirm the revised delivery arrangement for this order.')
            ->assertJsonPath('conversation.order_url', route('seller.orders.show', $offer))
            ->assertJsonPath('conversation.rfq_url', route('seller.rfqs.show', $offer->rfq_id))
            ->assertJsonPath('conversation.can_send_messages', true);

        $this->actingAs($seller)
            ->postJson(route('messenger.conversations.read', $offer))
            ->assertOk()
            ->assertJsonPath('unread_count', 0);

        $this->actingAs($seller)
            ->post(route('messenger.conversations.messages.store', $offer), [
                'body' => 'Confirmed. We can proceed with the selected scope.',
            ])
            ->assertCreated()
            ->assertJsonPath('conversation.messages.1.body', 'Confirmed. We can proceed with the selected scope.');

        $this->actingAs($buyer)
            ->getJson(route('messenger.conversations.show', $offer))
            ->assertOk()
            ->assertJsonCount(2, 'conversation.messages')
            ->assertJsonPath('conversation.messages.1.body', 'Confirmed. We can proceed with the selected scope.');
    }

    public function test_supplier_can_send_message_with_small_attachment(): void
    {
        Storage::fake('public');

        [$buyer, $seller, $offer] = $this->createAwardedOrder();

        $this->actingAs($seller)
            ->post(route('messenger.conversations.messages.store', $offer), [
                'body' => 'Please find the signed copy attached.',
                'attachment' => UploadedFile::fake()->create('signed-copy.pdf', 180, 'application/pdf'),
            ])
            ->assertCreated()
            ->assertJsonPath('conversation.messages.0.attachment.name', 'signed-copy.pdf');

        $message = OfferMessage::query()->firstOrFail();

        $this->assertSame($offer->id, $message->offer_id);
        Storage::disk('public')->assertExists($message->attachment_path);

        $this->actingAs($buyer)
            ->getJson(route('messenger.conversations.show', $offer))
            ->assertOk()
            ->assertJsonPath('conversation.messages.0.attachment.name', 'signed-copy.pdf');
    }

    public function test_unawarded_or_foreign_orders_are_not_accessible_in_messenger(): void
    {
        [$buyer, $seller, $offer] = $this->createAwardedOrder();
        [$otherBuyer] = $this->createAwardedOrder();
        [, $otherSeller, $openOffer] = $this->createOpenOffer();

        $this->actingAs($otherBuyer)
            ->getJson(route('messenger.conversations.show', $offer))
            ->assertNotFound();

        $this->actingAs($seller)
            ->getJson(route('messenger.conversations.show', $openOffer))
            ->assertNotFound();

        $this->actingAs($otherSeller)
            ->post(route('messenger.conversations.messages.store', $offer), [
                'body' => 'This should never be accepted.',
            ])
            ->assertNotFound();
    }

    public function test_completed_orders_become_view_only_and_admin_is_read_only(): void
    {
        [$buyer, $seller, $offer] = $this->createAwardedOrder([
            'order_workflow_status' => Offer::ORDER_STATUS_COMPLETED,
        ]);

        $admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Platform Admin',
        ]);

        $this->actingAs($buyer)
            ->getJson(route('messenger.conversations.show', $offer))
            ->assertOk()
            ->assertJsonPath('conversation.can_send_messages', false);

        $this->actingAs($buyer)
            ->post(route('messenger.conversations.messages.store', $offer), [
                'body' => 'Completed orders should not allow new chat activity.',
            ])
            ->assertForbidden();

        $this->actingAs($seller)
            ->post(route('messenger.conversations.messages.store', $offer), [
                'body' => 'Completed orders should stay locked for suppliers too.',
            ])
            ->assertForbidden();

        $this->actingAs($admin)
            ->getJson(route('messenger.conversations.show', $offer))
            ->assertOk()
            ->assertJsonPath('conversation.can_send_messages', false)
            ->assertJsonPath('conversation.counterparty_name', 'North Fleet');

        $this->actingAs($admin)
            ->post(route('messenger.conversations.messages.store', $offer), [
                'body' => 'Admins are read only in messenger.',
            ])
            ->assertForbidden();
    }

    private function createAwardedOrder(array $offerOverrides = []): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'name' => 'Buyer Contact',
            'company_name' => 'North Fleet',
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'name' => 'Supplier Contact',
            'company_name' => 'Atlas Marine',
            'approval_status' => 'approved',
            'approved_at' => now(),
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-MSG-'.strtoupper((string) str()->random(8)),
            'company_name' => 'North Fleet',
            'ship_name' => 'MV Messenger',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
            'country_names' => ['Turkey'],
            'ports_by_country' => [
                'Turkey' => [
                    ['id' => 1, 'name' => 'Istanbul', 'unlocode' => 'TRIST'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(5)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_CLOSED,
            'general_notes' => 'Messenger workflow test.',
            'service_title' => 'Pump overhaul attendance',
            'service_description' => 'Messenger order test.',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $offer = Offer::query()->create(array_merge([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'grand_total' => 1500,
            'payment_order_confirmation' => 50,
            'payment_before_shipment' => 50,
            'order_workflow_status' => Offer::ORDER_STATUS_INVOICE_PENDING,
            'submitted_at' => now(),
        ], $offerOverrides));

        OfferAward::query()->create([
            'rfq_id' => $rfq->id,
            'buyer_id' => $buyer->id,
            'offer_id' => $offer->id,
            'offer_item_id' => null,
            'rfq_item_id' => null,
            'request_type' => 'service_request',
            'status' => OfferAward::STATUS_CONFIRMED,
            'awarded_quantity' => 1,
            'buyer_note' => 'Proceed with the supplier.',
            'confirmed_at' => now(),
        ]);

        return [$buyer, $seller, $offer, $rfq];
    }

    private function createOpenOffer(): array
    {
        $buyer = User::factory()->create([
            'role' => 'buyer',
            'company_name' => 'Open Buyer',
        ]);

        $seller = User::factory()->create([
            'role' => 'seller',
            'company_name' => 'Open Supplier',
        ]);

        $rfq = Rfq::query()->create([
            'buyer_id' => $buyer->id,
            'reference_no' => 'RFQ-OPEN-'.strtoupper((string) str()->random(8)),
            'company_name' => 'Open Buyer',
            'ship_name' => 'MV Open',
            'request_type' => 'service_request',
            'visibility_scope' => Rfq::VISIBILITY_PUBLIC_MARKETPLACE,
            'country_name' => 'Turkey',
            'port_name' => 'Istanbul',
            'country_names' => ['Turkey'],
            'ports_by_country' => [
                'Turkey' => [
                    ['id' => 1, 'name' => 'Istanbul', 'unlocode' => 'TRIST'],
                ],
            ],
            'requisition_date' => now()->toDateString(),
            'due_date' => now()->addDays(7)->toDateString(),
            'currency' => 'USD',
            'priority' => 'normal',
            'status' => Rfq::STATUS_SUBMITTED,
            'service_title' => 'Open service request',
            'items_count' => 1,
            'submitted_at' => now(),
        ]);

        $offer = Offer::query()->create([
            'rfq_id' => $rfq->id,
            'seller_id' => $seller->id,
            'request_type' => 'service_request',
            'currency' => 'USD',
            'status' => Offer::STATUS_SUBMITTED,
            'grand_total' => 800,
            'submitted_at' => now(),
        ]);

        return [$buyer, $seller, $offer, $rfq];
    }
}
