<?php

namespace App\Http\Controllers;

use App\Models\Offer;
use App\Models\OfferAward;
use App\Support\BuyerDashboardData;
use App\Support\MarketplaceNotificationCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BuyerOrderDetailController extends Controller
{
    public function show(Request $request, Offer $offer, BuyerDashboardData $dashboardData): Response
    {
        $user = $request->user();

        abort_unless($user?->isBuyer(), 403);
        abort_unless($this->buyerOwnsConfirmedOrder($user->id, $offer), 404);

        $order = $dashboardData->order($user, $offer);

        abort_unless($order, 404);

        $summary = $dashboardData->requestSummary($user);
        $reviewSummary = $dashboardData->reviewSummary($user);
        $orderSummary = $dashboardData->orderSummary($user);

        return Inertia::render('Buyer/Dashboard/OrderDetail', [
            'dashboard' => $dashboardData->dashboard($summary, $reviewSummary, $orderSummary),
            'order' => $order,
        ]);
    }

    public function updateInformation(Request $request, Offer $offer): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user?->isBuyer() || $user?->isAdmin(), 403);
        abort_unless($this->canManageOrderInformation($user, $offer), 404);
        abort_unless($offer->canBuyerEditOrderInformation(), 403);

        $offer->loadMissing('rfq');
        $wasInitialOrderInformationSave = in_array($offer->orderWorkflowStatus(), [
            null,
            Offer::ORDER_STATUS_ORDER_INFORMATION_PENDING,
        ], true);

        $validated = $request->validate($this->orderInformationRules($offer));

        $payload = [
            'billing_company_name' => $this->nullableTrimmed($validated['billing_company_name'] ?? null),
            'billing_address' => $this->nullableTrimmed($validated['billing_address'] ?? null),
            'billing_tax_id' => $this->nullableTrimmed($validated['billing_tax_id'] ?? null),
            'billing_contact_name' => $this->nullableTrimmed($validated['billing_contact_name'] ?? null),
            'billing_contact_email' => $this->nullableTrimmed($validated['billing_contact_email'] ?? null),
            'billing_contact_phone' => $this->nullableTrimmed($validated['billing_contact_phone'] ?? null),
            'order_workflow_status' => Offer::ORDER_STATUS_INVOICE_PENDING,
        ];

        if ($offer->request_type === 'spare_parts') {
            $payload = array_merge($payload, [
                'delivery_target_type' => $validated['delivery_target_type'] ?? null,
                'delivery_country' => $this->nullableTrimmed($validated['delivery_country'] ?? null),
                'delivery_port' => $this->nullableTrimmed($validated['delivery_port'] ?? null),
                'delivery_address' => $this->nullableTrimmed($validated['delivery_address'] ?? null),
                'delivery_contact_name' => $this->nullableTrimmed($validated['delivery_contact_name'] ?? null),
                'delivery_contact_email' => $this->nullableTrimmed($validated['delivery_contact_email'] ?? null),
                'delivery_contact_phone' => $this->nullableTrimmed($validated['delivery_contact_phone'] ?? null),
                'delivery_required_date' => $validated['delivery_required_date'] ?? null,
                'service_location_type' => null,
                'service_location' => null,
                'service_contact_name' => null,
                'service_contact_email' => null,
                'service_contact_phone' => null,
                'service_required_date' => null,
                'service_instruction_notes' => null,
            ]);
        } else {
            $payload = array_merge($payload, [
                'delivery_target_type' => null,
                'delivery_country' => null,
                'delivery_port' => null,
                'delivery_address' => null,
                'delivery_contact_name' => null,
                'delivery_contact_email' => null,
                'delivery_contact_phone' => null,
                'delivery_required_date' => null,
                'service_location_type' => $validated['service_location_type'] ?? null,
                'service_location' => $this->nullableTrimmed($validated['service_location'] ?? null),
                'service_contact_name' => $this->nullableTrimmed($validated['service_contact_name'] ?? null),
                'service_contact_email' => $this->nullableTrimmed($validated['service_contact_email'] ?? null),
                'service_contact_phone' => $this->nullableTrimmed($validated['service_contact_phone'] ?? null),
                'service_required_date' => $validated['service_required_date'] ?? null,
                'service_instruction_notes' => $this->nullableTrimmed($validated['service_instruction_notes'] ?? null),
            ]);
        }

        $offer->update($payload);
        MarketplaceNotificationCenter::notifyBuyerOrderInformationSaved($offer, $wasInitialOrderInformationSave);
        MarketplaceNotificationCenter::notifySellerOrderInformationSaved($offer, $wasInitialOrderInformationSave);

        $returnTo = (string) $request->input('return_to', 'detail');

        $targetRoute = $this->targetRoute($user, $offer, $returnTo);

        return redirect($targetRoute)
            ->with('success', [
                'code' => $wasInitialOrderInformationSave
                    ? 'order-information-saved'
                    : 'order-information-updated',
            ]);
    }

    private function buyerOwnsConfirmedOrder(int $buyerId, Offer $offer): bool
    {
        return OfferAward::query()
            ->where('offer_id', $offer->id)
            ->where('buyer_id', $buyerId)
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->exists();
    }

    private function confirmedOrderExists(Offer $offer): bool
    {
        return OfferAward::query()
            ->where('offer_id', $offer->id)
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->exists();
    }

    private function canManageOrderInformation(?object $user, Offer $offer): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return $this->confirmedOrderExists($offer);
        }

        return $user->isBuyer() && $this->buyerOwnsConfirmedOrder($user->id, $offer);
    }

    private function orderInformationRules(Offer $offer): array
    {
        $rules = [
            'billing_company_name' => ['required', 'string', 'max:255'],
            'billing_address' => ['required', 'string', 'max:2000'],
            'billing_tax_id' => ['nullable', 'string', 'max:120'],
            'billing_contact_name' => ['required', 'string', 'max:120'],
            'billing_contact_email' => ['required', 'email', 'max:255'],
            'billing_contact_phone' => ['required', 'string', 'max:60'],
        ];

        if ($offer->request_type === 'spare_parts') {
            return array_merge($rules, [
                'delivery_target_type' => ['required', Rule::in(['vessel', 'warehouse', 'office', 'agent', 'other'])],
                'delivery_country' => ['required', 'string', 'max:120'],
                'delivery_port' => ['required', 'string', 'max:120'],
                'delivery_address' => ['required', 'string', 'max:2000'],
                'delivery_contact_name' => ['required', 'string', 'max:120'],
                'delivery_contact_email' => ['required', 'email', 'max:255'],
                'delivery_contact_phone' => ['required', 'string', 'max:60'],
                'delivery_required_date' => ['required', 'date'],
            ]);
        }

        return array_merge($rules, [
            'service_location_type' => ['required', Rule::in(['on_board', 'port', 'yard', 'other'])],
            'service_location' => ['required', 'string', 'max:255'],
            'service_contact_name' => ['required', 'string', 'max:120'],
            'service_contact_email' => ['required', 'email', 'max:255'],
            'service_contact_phone' => ['required', 'string', 'max:60'],
            'service_required_date' => ['required', 'date'],
            'service_instruction_notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function nullableTrimmed(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed !== '' ? $trimmed : null;
    }

    private function targetRoute(object $user, Offer $offer, string $returnTo): string
    {
        if ($user->isAdmin()) {
            return $returnTo === 'orders'
                ? route('admin.orders')
                : route('admin.orders.show', $offer);
        }

        return $returnTo === 'orders'
            ? route('buyer.orders')
            : route('buyer.orders.show', $offer);
    }
}
