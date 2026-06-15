<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rfq;
use App\Support\AdminDashboardData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminRfqController extends Controller
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

        $rfqs = $dashboardData->rfqPage($filters, $perPage);

        return Inertia::render('Admin/Dashboard/Rfqs', [
            'dashboard' => $dashboardData->dashboard(),
            'rfqsTable' => [
                'data' => $rfqs->items(),
                'meta' => $this->paginationMeta($rfqs),
                'filters' => $filters,
            ],
        ]);
    }

    public function show(Request $request, Rfq $rfq, AdminDashboardData $dashboardData): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $detail = $dashboardData->rfq($rfq);

        abort_unless($detail, 404);

        return Inertia::render('Admin/Dashboard/RfqDetail', [
            'dashboard' => $dashboardData->dashboard(),
            'rfq' => $detail,
            'backUrl' => route('admin.rfqs'),
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
