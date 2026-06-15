<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Offer;
use App\Support\AdminDashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminOrderController extends Controller
{
    public function index(Request $request, AdminDashboardData $dashboardData): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $perPage = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPage, [10, 25, 50], true) ? $perPage : 10;

        $filters = [
            'search' => trim((string) $request->string('search')->value()),
            'sort' => $request->string('sort')->value() ?: 'latest',
            'page' => max(1, (int) $request->integer('page', 1)),
            'per_page' => $perPage,
        ];

        $orders = $dashboardData->ordersPage($filters, $perPage);

        return Inertia::render('Admin/Dashboard/Orders', [
            'dashboard' => $dashboardData->dashboard(),
            'ordersTable' => [
                'data' => $orders->items(),
                'meta' => $this->paginationMeta($orders),
                'filters' => $filters,
            ],
        ]);
    }

    public function show(Request $request, Offer $offer, AdminDashboardData $dashboardData): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $order = $dashboardData->order($offer);

        abort_unless($order, 404);

        return Inertia::render('Admin/Dashboard/OrderDetail', [
            'dashboard' => $dashboardData->dashboard(),
            'order' => $order,
        ]);
    }

    public function modal(Request $request, Offer $offer, AdminDashboardData $dashboardData): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $order = $dashboardData->order($offer);

        abort_unless($order, 404);

        return response()->json([
            'order' => $order,
        ]);
    }

    private function paginationMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
        ];
    }
}
