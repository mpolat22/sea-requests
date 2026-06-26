<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Subcategory;
use App\Models\User;
use App\Support\ServiceRoute;
use App\Support\AdminDashboardData;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminDashboardController extends Controller
{
    public function __invoke(Request $request, AdminDashboardData $dashboardData): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $activeTab = $request->string('tab')->value() === 'users' ? 'users' : 'businesses';

        $userFilters = [
            'search' => trim((string) $request->string('user_search')->value()),
            'sort' => $request->string('user_sort')->value() ?: 'latest',
            'page' => max(1, (int) $request->integer('user_page', 1)),
        ];

        $businessFilters = [
            'search' => trim((string) $request->string('business_search')->value()),
            'sort' => $request->string('business_sort')->value() ?: 'latest',
            'page' => max(1, (int) $request->integer('business_page', 1)),
            'filter' => $request->string('business_filter')->value() ?: 'all',
        ];

        $userPaginator = $this->buildUsersPaginator($userFilters);
        $businessPaginator = $this->buildBusinessesPaginator($businessFilters);

        $allVisibleUsers = $userPaginator->getCollection()->concat($businessPaginator->getCollection());
        $this->decorateUsers($allVisibleUsers);

        return Inertia::render('Admin/Dashboard/Dashboard', [
            'dashboard' => $dashboardData->dashboard(),
            'activeTab' => $activeTab,
            'userTable' => [
                'data' => $userPaginator->items(),
                'meta' => $this->paginationMeta($userPaginator),
                'filters' => $userFilters,
            ],
            'businessTable' => [
                'data' => $businessPaginator->items(),
                'meta' => $this->paginationMeta($businessPaginator),
                'filters' => $businessFilters,
                'counts' => $this->businessFilterCounts(),
            ],
        ]);
    }

    private function buildUsersPaginator(array $filters): LengthAwarePaginator
    {
        $query = User::query()
            ->where('role', '!=', 'admin');

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('company_name', 'like', "%{$term}%");
            });
        }

        $this->applyUserSort($query, $filters['sort']);

        return $query
            ->paginate(10, $this->selectColumns(), 'user_page', $filters['page'])
            ->withQueryString();
    }

    private function buildBusinessesPaginator(array $filters): LengthAwarePaginator
    {
        $query = $this->supplierRegistrationQuery();

        if ($filters['filter'] === 'pending') {
            $query->where('approval_status', 'pending');
        } elseif ($filters['filter'] === 'approved') {
            $query->where('approval_status', 'approved');
        } elseif ($filters['filter'] === 'rejected') {
            $query->where('approval_status', 'rejected');
        } elseif ($filters['filter'] === 'update-pending') {
            $query->where('seller_update_request_status', 'pending');
        } elseif ($filters['filter'] === 'removal') {
            $query->whereNotNull('seller_removal_requested_at');
        }

        if ($filters['search'] !== '') {
            $term = $filters['search'];
            $query->where(function (Builder $builder) use ($term) {
                $builder
                    ->where('company_name', 'like', "%{$term}%")
                    ->orWhere('name', 'like', "%{$term}%")
                    ->orWhere('country', 'like', "%{$term}%")
                    ->orWhere('company_city', 'like', "%{$term}%")
                    ->orWhere('approval_status', 'like', "%{$term}%");
            });
        }

        $this->applyBusinessSort($query, $filters['sort']);

        return $query
            ->paginate(10, $this->selectColumns(), 'business_page', $filters['page'])
            ->withQueryString();
    }

    private function applyUserSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('id'),
            'name-asc' => $query->orderBy('name')->orderByDesc('id'),
            'name-desc' => $query->orderByDesc('name')->orderByDesc('id'),
            'email-asc' => $query->orderBy('email')->orderByDesc('id'),
            default => $query->latest('id'),
        };
    }

    private function applyBusinessSort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest('id'),
            'company-asc' => $query->orderByRaw('COALESCE(company_name, name) asc')->orderByDesc('id'),
            'company-desc' => $query->orderByRaw('COALESCE(company_name, name) desc')->orderByDesc('id'),
            'status' => $query->orderBy('approval_status')->orderByDesc('id'),
            default => $query->latest('id'),
        };
    }

    private function selectColumns(): array
    {
        return [
            'id',
            'name',
            'company_name',
            'email',
            'role',
            'approval_status',
            'email_verified_at',
            'seller_verification_submitted_at',
            'seller_verification_onboarding_sent_at',
            'seller_verification_24h_reminder_sent_at',
            'seller_verification_72h_reminder_sent_at',
            'country',
            'company_city',
            'company_address_line',
            'phone',
            'whatsapp_number',
            'company_description',
            'contact_email',
            'website_url',
            'service_category_ids',
            'service_subcategory_ids',
            'seller_rejection_reason',
            'seller_rejection_note',
            'seller_rejection_fields',
            'seller_rejected_at',
            'seller_update_request_status',
            'seller_update_request_payload',
            'seller_update_request_diff',
            'seller_update_requested_at',
            'seller_update_rejection_reason',
            'seller_update_rejection_note',
            'seller_update_rejection_fields',
            'seller_update_rejected_at',
            'seller_removal_request_reason',
            'seller_removal_request_note',
            'seller_removal_request_status',
            'seller_removal_requested_at',
            'created_at',
        ];
    }

    private function businessFilterCounts(): array
    {
        $query = $this->supplierRegistrationQuery();

        return [
            'all' => (clone $query)->count(),
            'pending' => (clone $query)->where('approval_status', 'pending')->count(),
            'approved' => (clone $query)->where('approval_status', 'approved')->count(),
            'rejected' => (clone $query)->where('approval_status', 'rejected')->count(),
            'update-pending' => (clone $query)->where('seller_update_request_status', 'pending')->count(),
            'removal' => (clone $query)->whereNotNull('seller_removal_requested_at')->count(),
        ];
    }

    private function supplierRegistrationQuery(): Builder
    {
        return User::query()
            ->where('role', 'seller')
            ->where(function (Builder $builder) {
                $builder
                    ->whereNotNull('company_name')
                    ->orWhereNotNull('seller_verification_onboarding_sent_at')
                    ->orWhereNotNull('seller_verification_submitted_at')
                    ->orWhere('approval_status', '!=', 'pending')
                    ->orWhereNotNull('seller_update_request_status')
                    ->orWhereNotNull('seller_removal_requested_at');
            });
    }

    private function decorateUsers($users): void
    {
        $categoryLookup = Category::query()
            ->whereIn('id', $users->flatMap(fn (User $user) => $user->service_category_ids ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values())
            ->get(['id', 'name', 'slug'])
            ->keyBy('id');

        $subcategoryLookup = Subcategory::query()
            ->whereIn('id', $users->flatMap(fn (User $user) => $user->service_subcategory_ids ?? [])->filter()->map(fn ($id) => (int) $id)->unique()->values())
            ->get(['id', 'name', 'slug', 'category_id'])
            ->groupBy('category_id');

        $users->transform(function (User $user) use ($categoryLookup, $subcategoryLookup) {
            $categoryIds = collect($user->service_category_ids ?? [])->map(fn ($id) => (int) $id)->filter()->values();
            $primaryCategory = $categoryLookup->get($categoryIds->first());

            $matchingSubcategory = $primaryCategory
                ? collect($subcategoryLookup->get($primaryCategory->id, collect()))
                    ->first(fn (Subcategory $subcategory) => in_array((int) $subcategory->id, array_map('intval', $user->service_subcategory_ids ?? []), true))
                : null;

            $user->setAttribute(
                'preview_url',
                $primaryCategory ? ServiceRoute::url($user, $primaryCategory, $matchingSubcategory, 'admin.services.preview') : null
            );
            $user->setAttribute('edit_url', route('admin.seller-verification.edit', $user));
            $user->setAttribute('has_pending_update_request', $user->seller_update_request_status === 'pending');
            $user->setAttribute('update_diff', $user->seller_update_request_diff ?? []);
            $user->setAttribute('update_changed_fields', array_keys($user->seller_update_request_diff ?? []));

            return $user;
        });
    }

    private function paginationMeta(LengthAwarePaginator $paginator): array
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
