<?php

use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminOrderController;
use App\Http\Controllers\Admin\AdminOutreachController;
use App\Http\Controllers\Admin\AdminRfqController;
use App\Http\Controllers\Admin\AdminUserManagementController;
use App\Http\Controllers\Admin\UserApprovalController;
use App\Http\Controllers\ApprovalPendingController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Auth\VerifyEmailController;
use App\Http\Controllers\BuyerDashboardController;
use App\Http\Controllers\BuyerProfileController;
use App\Http\Controllers\BuyerOrderDetailController;
use App\Http\Controllers\BuyerOrderPaymentProofController;
use App\Http\Controllers\BuyerOrdersController;
use App\Http\Controllers\BuyerRequestsController;
use App\Http\Controllers\BuyerReviewsController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationIndexController;
use App\Http\Controllers\OrderMessengerController;
use App\Http\Controllers\RfqController;
use App\Http\Controllers\SellerDashboardController;
use App\Http\Controllers\SellerAwardedRequestsController;
use App\Http\Controllers\SellerAwardDetailController;
use App\Http\Controllers\SellerIncomingRequestsController;
use App\Http\Controllers\SellerOrderInvoiceController;
use App\Http\Controllers\SellerOrderPaymentConfirmationController;
use App\Http\Controllers\SellerReviewsController;
use App\Http\Controllers\SellerVerificationController;
use App\Http\Controllers\SupplierReviewController;
use App\Models\Brand;
use App\Models\Category;
use App\Models\OfferAward;
use App\Models\Port;
use App\Models\Rfq;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\SupplierServiceListingPort;
use App\Models\User;
use App\Support\CountryNameResolver;
use App\Support\ServiceDirectoryRoute;
use App\Support\ServiceRoute;
use App\Support\SupplierReviewData;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Inertia;

Route::get('/', HomeController::class)->name('home');

Route::get('/requests', [RfqController::class, 'index'])->name('requests.index');
Route::get('/requests/{rfq}', [RfqController::class, 'legacyShow'])->whereNumber('rfq');
Route::get('/requests/{rfq}-{slug}/similar', [RfqController::class, 'similar'])
    ->whereNumber('rfq')
    ->name('rfqs.similar');
Route::get('/requests/{rfq}-{slug}', [RfqController::class, 'show'])
    ->whereNumber('rfq')
    ->name('rfqs.show');
Route::get('/outreach/unsubscribe/{contact}', [AdminOutreachController::class, 'unsubscribe'])
    ->middleware('signed')
    ->name('outreach.unsubscribe');
Route::post('/outreach/unsubscribe/{contact}', [AdminOutreachController::class, 'unsubscribe'])
    ->middleware('signed')
    ->name('outreach.unsubscribe.confirm');

Route::get('/robots.txt', function (Request $request) {
    $content = implode("\n", [
        'User-agent: *',
        'Allow: /',
        'Disallow: /admin',
        'Disallow: /dashboard',
        'Disallow: /notifications',
        'Sitemap: '.rtrim($request->root(), '/').'/sitemap.xml',
    ]);

    return Response::make($content, 200, [
        'Content-Type' => 'text/plain; charset=UTF-8',
        'Cache-Control' => 'public, max-age=3600',
    ]);
});

Route::get('/sitemap.xml', function (Request $request) {
    $root = rtrim($request->root(), '/');
    $toTimestamp = static function ($value): ?int {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        try {
            return Carbon::parse($value)->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    };

    $fileLastmod = static function (array $paths): ?int {
        return collect($paths)
            ->map(function (string $path) {
                $resolvedPath = str_starts_with($path, base_path())
                    ? $path
                    : base_path($path);

                return File::exists($resolvedPath) ? File::lastModified($resolvedPath) : null;
            })
            ->filter()
            ->max();
    };

    $lastmodFrom = static function (...$values) use ($toTimestamp): string {
        $timestamps = collect($values)
            ->flatten()
            ->map($toTimestamp)
            ->filter()
            ->values();

        return $timestamps->isNotEmpty()
            ? Carbon::createFromTimestamp($timestamps->max())->toDateString()
            : now()->toDateString();
    };

    $homeLastmod = $lastmodFrom(
        $fileLastmod([
            'app/Http/Controllers/HomeController.php',
            'resources/js/Pages/Home.vue',
        ]),
        Rfq::query()->published()->publicMarketplace()->max('updated_at'),
        SupplierServiceListing::query()->visible()->max('updated_at'),
        Category::query()->where('is_active', true)->max('updated_at'),
        Subcategory::query()->where('is_active', true)->max('updated_at')
    );

    $servicesLastmod = $lastmodFrom(
        $fileLastmod([
            'routes/web.php',
            'resources/js/Pages/Service/ServicesIndex.vue',
        ]),
        SupplierServiceListing::query()->visible()->max('updated_at'),
        Category::query()->where('is_active', true)->max('updated_at'),
        Subcategory::query()->where('is_active', true)->max('updated_at')
    );

    $requestsLastmod = $lastmodFrom(
        $fileLastmod([
            'app/Http/Controllers/RfqController.php',
            'resources/js/Pages/Request/RequestsIndex.vue',
        ]),
        Rfq::query()->published()->publicMarketplace()->max('updated_at')
    );

    $staticInfoLastmod = $lastmodFrom(
        $fileLastmod([
            'resources/js/Pages/Static/AboutUs.vue',
            'resources/js/Pages/Static/Blog.vue',
            'resources/js/Pages/Static/Contact.vue',
            'resources/js/Pages/Static/Disclaimer.vue',
            'resources/js/Pages/Static/Faq.vue',
            'resources/js/Pages/Static/StaticPageLayout.vue',
            'app/Http/Controllers/ContactMessageController.php',
            'resources/views/emails/contact-message.blade.php',
        ])
    );

    $privacyLastmod = $lastmodFrom(
        $fileLastmod([
            'resources/js/Pages/Static/PrivacyPolicy.vue',
            'resources/js/Pages/Static/StaticPageLayout.vue',
        ])
    );

    $termsLastmod = $lastmodFrom(
        $fileLastmod([
            'resources/js/Pages/Static/TermsAndConditions.vue',
            'resources/js/Pages/Static/StaticPageLayout.vue',
        ])
    );

    $allUrls = collect([
        ['loc' => $root.'/', 'lastmod' => $homeLastmod, 'changefreq' => 'weekly', 'priority' => '1.0'],
        ['loc' => $root.'/services', 'lastmod' => $servicesLastmod, 'changefreq' => 'weekly', 'priority' => '0.9'],
        ['loc' => $root.'/requests', 'lastmod' => $requestsLastmod, 'changefreq' => 'weekly', 'priority' => '0.8'],
        ['loc' => $root.'/about-us', 'lastmod' => $staticInfoLastmod, 'changefreq' => 'monthly', 'priority' => '0.45'],
        ['loc' => $root.'/blog', 'lastmod' => $staticInfoLastmod, 'changefreq' => 'monthly', 'priority' => '0.4'],
        ['loc' => $root.'/contact', 'lastmod' => $staticInfoLastmod, 'changefreq' => 'monthly', 'priority' => '0.55'],
        ['loc' => $root.'/faq', 'lastmod' => $staticInfoLastmod, 'changefreq' => 'monthly', 'priority' => '0.45'],
        ['loc' => $root.'/disclaimer', 'lastmod' => $staticInfoLastmod, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ['loc' => $root.'/privacy-policy', 'lastmod' => $privacyLastmod, 'changefreq' => 'yearly', 'priority' => '0.3'],
        ['loc' => $root.'/terms-of-service', 'lastmod' => $termsLastmod, 'changefreq' => 'yearly', 'priority' => '0.3'],
    ])
        ->map(function (array $item) {
            $loc = htmlspecialchars($item['loc'], ENT_XML1);
            $lastmod = htmlspecialchars($item['lastmod'], ENT_XML1);
            $changefreq = htmlspecialchars($item['changefreq'], ENT_XML1);
            $priority = htmlspecialchars($item['priority'], ENT_XML1);

            return <<<XML
    <url>
        <loc>{$loc}</loc>
        <lastmod>{$lastmod}</lastmod>
        <changefreq>{$changefreq}</changefreq>
        <priority>{$priority}</priority>
    </url>
XML;
        })
        ->implode("\n");

    $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$allUrls}
</urlset>
XML;

    return Response::make($xml, 200, [
        'Content-Type' => 'application/xml; charset=UTF-8',
        'Cache-Control' => 'public, max-age=900',
    ]);
});

$storageUrl = static fn (?string $path) => $path ? '/storage/'.ltrim($path, '/') : null;

$mapSellerPortsByCountry = static function (User $seller) {
    $ports = $seller->relationLoaded('servicePorts')
        ? $seller->servicePorts
        : $seller->servicePorts()
            ->orderBy('country_name')
            ->orderBy('port_name')
            ->get(['ports.id', 'ports.country_code', 'ports.country_name', 'ports.port_name', 'ports.unlocode']);

    return $ports
        ->groupBy('country_code')
        ->map(fn ($groupedPorts) => [
            'country_code' => $groupedPorts->first()?->country_code,
            'country_name' => CountryNameResolver::resolve((string) ($groupedPorts->first()?->country_code ?? $groupedPorts->first()?->country_name)),
            'ports' => $groupedPorts->map(fn ($port) => [
                'id' => $port->id,
                'port_name' => $port->port_name,
                'unlocode' => $port->unlocode,
            ])->values(),
        ])
        ->values();
};

$buildServiceCards = static function (
    User $seller,
    ?\Illuminate\Support\Collection $categoriesById = null,
    ?\Illuminate\Support\Collection $subcategoriesById = null,
    string $routeName = 'services.show'
) use ($storageUrl) {

    $sellerCategoryIds = collect($seller->service_category_ids ?? [])
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values();
    $sellerSubcategoryIds = collect($seller->service_subcategories_by_category ?? [])
        ->flatten(1)
        ->merge($seller->service_subcategory_ids ?? [])
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->unique()
        ->values();

    $sellerCategories = $categoriesById
        ? $sellerCategoryIds
            ->map(fn (int $id) => $categoriesById->get($id))
            ->filter()
            ->sortBy('name')
            ->values()
        : Category::query()
            ->whereIn('id', $sellerCategoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    $sellerSubcategories = $subcategoriesById
        ? $sellerSubcategoryIds
            ->map(fn (int $id) => $subcategoriesById->get($id))
            ->filter()
            ->sortBy('name')
            ->values()
        : Subcategory::query()
            ->whereIn('id', $sellerSubcategoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category_id']);

    return $sellerCategories->flatMap(function (Category $category) use ($sellerSubcategories, $seller, $storageUrl, $routeName) {
        $groupedSubcategoryIds = collect($seller->service_subcategories_by_category ?? [])
            ->get((string) $category->id, []);

        $resolvedSubcategoryIds = collect($groupedSubcategoryIds)
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();

        if ($resolvedSubcategoryIds->isEmpty()) {
            $resolvedSubcategoryIds = $sellerSubcategories
                ->filter(fn (Subcategory $subcategory) => (int) $subcategory->category_id === (int) $category->id)
                ->pluck('id')
                ->map(fn ($value) => (int) $value)
                ->values();
        }

        $matchingSubcategories = $sellerSubcategories
            ->filter(fn (Subcategory $subcategory) => (int) $subcategory->category_id === (int) $category->id)
            ->filter(fn (Subcategory $subcategory) => $resolvedSubcategoryIds->contains((int) $subcategory->id))
            ->values();

        if ($matchingSubcategories->isEmpty()) {
            return [[
                'id' => "{$seller->id}-category-{$category->id}",
                'seller_id' => $seller->id,
                'title' => $category->name,
                'company_name' => $seller->company_name ?: $seller->name,
                'country' => CountryNameResolver::resolve((string) $seller->country),
                'summary' => $seller->company_overview ?: $seller->company_description,
                'primary_category' => [
                    'id' => $category->id,
                    'name' => $category->name,
                    'slug' => $category->slug,
                ],
                'secondary_category' => null,
                'logo_url' => $storageUrl($seller->company_logo_path),
                'href' => ServiceRoute::url($seller, $category, null, $routeName),
            ]];
        }

        return $matchingSubcategories->map(fn (Subcategory $subcategory) => [
            'id' => "{$seller->id}-subcategory-{$subcategory->id}",
            'seller_id' => $seller->id,
            'title' => $subcategory->name,
            'company_name' => $seller->company_name ?: $seller->name,
            'country' => CountryNameResolver::resolve((string) $seller->country),
            'summary' => $seller->company_overview ?: $seller->company_description,
            'primary_category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'secondary_category' => [
                'id' => $subcategory->id,
                'name' => $subcategory->name,
                'slug' => $subcategory->slug,
            ],
            'logo_url' => $storageUrl($seller->company_logo_path),
            'href' => ServiceRoute::url($seller, $category, $subcategory, $routeName),
        ])->all();
    })->values();
};

$buildCategoryScopedServiceCards = static function (
    User $seller,
    Category $category,
    ?\Illuminate\Support\Collection $subcategoriesById = null,
    string $routeName = 'services.show'
) use ($storageUrl) {
    $sellerCategoryIds = collect($seller->service_category_ids ?? [])
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values();

    if (! $sellerCategoryIds->contains((int) $category->id)) {
        return collect();
    }

    $subcategoryIds = collect($seller->service_subcategories_by_category ?? [])
        ->get((string) $category->id, []);

    $resolvedSubcategoryIds = collect($subcategoryIds)
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values();

    if ($resolvedSubcategoryIds->isEmpty()) {
        $resolvedSubcategoryIds = collect($seller->service_subcategory_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->values();
    }

    $matchingSubcategories = $subcategoriesById
        ? $resolvedSubcategoryIds
            ->map(fn (int $id) => $subcategoriesById->get($id))
            ->filter()
            ->sortBy('name')
            ->values()
        : Subcategory::query()
            ->where('category_id', $category->id)
            ->whereIn('id', $resolvedSubcategoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category_id']);

    if ($matchingSubcategories->isEmpty()) {
        return collect([[
            'id' => "{$seller->id}-category-{$category->id}",
            'seller_id' => $seller->id,
            'title' => $category->name,
            'company_name' => $seller->company_name ?: $seller->name,
            'country' => CountryNameResolver::resolve((string) $seller->country),
            'summary' => $seller->company_overview ?: $seller->company_description,
            'primary_category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ],
            'secondary_category' => null,
            'logo_url' => $storageUrl($seller->company_logo_path),
            'href' => ServiceRoute::url($seller, $category, null, $routeName),
        ]]);
    }

    return $matchingSubcategories->map(fn (Subcategory $subcategory) => [
        'id' => "{$seller->id}-subcategory-{$subcategory->id}",
        'seller_id' => $seller->id,
        'title' => $subcategory->name,
        'company_name' => $seller->company_name ?: $seller->name,
        'country' => CountryNameResolver::resolve((string) $seller->country),
        'summary' => $seller->company_overview ?: $seller->company_description,
        'primary_category' => [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ],
        'secondary_category' => [
            'id' => $subcategory->id,
            'name' => $subcategory->name,
            'slug' => $subcategory->slug,
        ],
        'logo_url' => $storageUrl($seller->company_logo_path),
        'href' => ServiceRoute::url($seller, $category, $subcategory, $routeName),
    ])->values();
};

$resolveServiceProfileContext = static function (string $category, string $subcategory, string $vendor): array {
    $userId = ServiceRoute::extractUserId($vendor);
    abort_unless($userId, 404);

    $user = User::query()->findOrFail($userId);
    abort_unless($user->role === 'seller' && $user->approval_status === 'approved', 404);

    $categoryIds = collect($user->service_category_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values();
    $subcategoryIds = collect($user->service_subcategories_by_category ?? [])
        ->flatten(1)
        ->merge($user->service_subcategory_ids ?? [])
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->unique()
        ->values();

    $categories = Category::query()->whereIn('id', $categoryIds)->orderBy('name')->get(['id', 'name', 'slug']);
    $subcategories = Subcategory::query()->whereIn('id', $subcategoryIds)->orderBy('name')->get(['id', 'name', 'slug', 'category_id']);
    $matchedListing = SupplierServiceListing::query()
        ->visible()
        ->where('seller_id', $user->id)
        ->where('vendor_slug', $vendor)
        ->where('category_slug', $category)
        ->where(function ($listingQuery) use ($subcategory, $category) {
            $listingQuery->where('subcategory_slug', $subcategory);

            if ($subcategory === $category) {
                $listingQuery->orWhereNull('subcategory_slug');
            }
        })
        ->first(['category_id', 'subcategory_id']);

    $selectedCategory = $categories->firstWhere('slug', $category);
    if (! $selectedCategory && $matchedListing?->category_id) {
        $selectedCategory = $categories->firstWhere('id', (int) $matchedListing->category_id)
            ?? Category::query()->find($matchedListing->category_id, ['id', 'name', 'slug']);
    }
    abort_unless($selectedCategory, 404);

    $groupedSubcategoryIds = collect($user->service_subcategories_by_category ?? [])
        ->get((string) $selectedCategory->id, []);

    $resolvedSubcategoryIds = collect($groupedSubcategoryIds)
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values();

    if ($resolvedSubcategoryIds->isEmpty()) {
        $resolvedSubcategoryIds = $subcategories
            ->filter(fn (Subcategory $item) => (int) $item->category_id === (int) $selectedCategory->id)
            ->pluck('id')
            ->map(fn ($value) => (int) $value)
            ->values();
    }

    $activeSubcategories = $subcategories
        ->filter(fn (Subcategory $item) => (int) $item->category_id === (int) $selectedCategory->id)
        ->filter(fn (Subcategory $item) => $resolvedSubcategoryIds->contains((int) $item->id))
        ->values();

    if ($matchedListing?->subcategory_id && ! $activeSubcategories->contains(fn (Subcategory $item) => (int) $item->id === (int) $matchedListing->subcategory_id)) {
        $matchedSubcategory = Subcategory::query()->find($matchedListing->subcategory_id, ['id', 'name', 'slug', 'category_id']);

        if ($matchedSubcategory && (int) $matchedSubcategory->category_id === (int) $selectedCategory->id) {
            $activeSubcategories = $activeSubcategories->push($matchedSubcategory)->unique('id')->values();
        }
    }

    $selectedSubcategory = null;
    if ($subcategory !== $selectedCategory->slug) {
        $selectedSubcategory = $activeSubcategories->firstWhere('slug', $subcategory);
        abort_unless($selectedSubcategory, 404);
    } elseif ($activeSubcategories->isNotEmpty()) {
        $selectedSubcategory = $activeSubcategories->first();
    }

    $expectedVendor = ServiceRoute::vendorSlug($user);
    $expectedSubcategory = $selectedSubcategory?->slug ?: $selectedCategory->slug;
    abort_unless($vendor === $expectedVendor && $category === $selectedCategory->slug && $subcategory === $expectedSubcategory, 404);

    return [
        'user' => $user,
        'categories' => $categories,
        'subcategories' => $subcategories,
        'selectedCategory' => $selectedCategory,
        'selectedSubcategory' => $selectedSubcategory,
    ];
};

$buildServiceReviewPayload = static function (User $seller, ?User $viewer, SupplierReviewData $reviewData): array {
    $isSellerOwnerView = $viewer?->isSeller() && (int) $viewer->id === (int) $seller->id;
    $reviewTargets = $viewer?->isBuyer()
        ? $reviewData->reviewTargetsForBuyer($viewer, $seller)
        : collect();
    $buyerEditableReviewOfferIds = $reviewTargets
        ->pluck('offer_id')
        ->map(fn ($value) => (int) $value)
        ->filter()
        ->values()
        ->all();

    $reviewItems = $reviewData->publicReviewsForSeller($seller, ! $isSellerOwnerView)
        ->map(fn (array $item) => [
            ...$item,
            'can_reply' => $isSellerOwnerView,
            'reply_url' => $isSellerOwnerView ? route('seller.reviews.reply', $item['id']) : null,
            'can_delete_reply' => $isSellerOwnerView && filled($item['seller_reply']),
            'delete_reply_url' => $isSellerOwnerView && filled($item['seller_reply'])
                ? route('seller.reviews.reply.destroy', $item['id'])
                : null,
            'can_delete_review' => $viewer?->isBuyer() && in_array((int) ($item['offer_id'] ?? 0), $buyerEditableReviewOfferIds, true),
            'delete_review_url' => $viewer?->isBuyer() && in_array((int) ($item['offer_id'] ?? 0), $buyerEditableReviewOfferIds, true)
                ? route('supplier-reviews.destroy', $item['id'])
                : null,
            'delete_modal_buyer_company' => $viewer?->isBuyer() && in_array((int) ($item['offer_id'] ?? 0), $buyerEditableReviewOfferIds, true)
                ? ($item['buyer_company_full'] ?: $item['buyer_company'])
                : $item['buyer_company'],
        ])
        ->values();

    return [
        'review_summary' => $reviewData->summaryForSeller($seller),
        'review_items' => $reviewItems,
        'review_eligibility' => [
            'access_state' => ! $viewer
                ? 'guest'
                : ($isSellerOwnerView ? 'owner' : ($reviewTargets->isNotEmpty() ? 'eligible' : 'restricted')),
            'can_submit_review' => $reviewTargets->isNotEmpty(),
            'submit_url' => route('supplier-reviews.store'),
            'targets' => $reviewTargets,
        ],
    ];
};

$buildSimilarVendorsPayload = static function (
    User $seller,
    Category $selectedCategory,
    string $routeName = 'services.show'
) use ($buildCategoryScopedServiceCards) {
    $selectedCategorySubcategoriesById = Subcategory::query()
        ->where('category_id', $selectedCategory->id)
        ->orderBy('name')
        ->get(['id', 'name', 'slug', 'category_id'])
        ->keyBy('id');

    return User::query()
        ->select([
            'id',
            'name',
            'company_name',
            'country',
            'company_overview',
            'company_description',
            'company_logo_path',
            'service_category_ids',
            'service_subcategory_ids',
            'service_subcategories_by_category',
        ])
        ->where('role', 'seller')
        ->where('approval_status', 'approved')
        ->where('id', '!=', $seller->id)
        ->whereJsonContains('service_category_ids', $selectedCategory->id)
        ->orderByRaw('COALESCE(company_name, name)')
        ->limit(10)
        ->get()
        ->flatMap(function (User $similarSeller) use ($buildCategoryScopedServiceCards, $selectedCategory, $selectedCategorySubcategoriesById, $routeName) {
            return Cache::remember(
                "service_profile_similar_v2_{$routeName}_{$similarSeller->id}_{$selectedCategory->id}",
                now()->addMinutes(15),
                fn () => $buildCategoryScopedServiceCards($similarSeller, $selectedCategory, $selectedCategorySubcategoriesById, $routeName)->all()
            );
        })
        ->take(10)
        ->values();
};

$mapListingCard = static function (SupplierServiceListing $listing, ?string $activeCountry = null) use ($storageUrl): array {
    $reviewCount = (int) ($listing->seller?->received_reviews_count ?? 0);
    $reviewAverage = $reviewCount > 0
        ? round((float) ($listing->seller?->received_reviews_average ?? 0), 1)
        : null;

    return [
        'id' => $listing->id,
        'seller_id' => $listing->seller_id,
        'name' => $listing->company_name,
        'company_name' => $listing->company_name,
        'contact_name' => $listing->contact_name,
        'country' => $listing->country,
        'display_country' => $activeCountry ?: $listing->country,
        'ports_count' => (int) ($listing->ports_count ?? 0),
        'review_summary' => [
            'count' => $reviewCount,
            'average' => $reviewAverage,
        ],
        'summary' => $listing->summary,
        'logo_url' => $storageUrl($listing->logo_path),
        'primary_category' => [
            'id' => $listing->category_id,
            'name' => $listing->category_name,
            'slug' => $listing->category_slug,
        ],
        'secondary_category' => $listing->subcategory_id ? [
            'id' => $listing->subcategory_id,
            'name' => $listing->subcategory_name,
            'slug' => $listing->subcategory_slug,
        ] : null,
        'href' => route('services.show', [
            'category' => $listing->category_slug,
            'subcategory' => $listing->subcategory_slug ?: $listing->category_slug,
            'vendor' => $listing->vendor_slug,
        ]),
    ];
};

$servicePerformanceCache = Cache::store('file');

$getServiceDirectoryFilterPayload = static function () use ($servicePerformanceCache): array {
    $aggregateVersion = static function ($query): array {
        $stats = $query
            ->selectRaw('COUNT(*) as aggregate_count, MAX(updated_at) as aggregate_max')
            ->first();

        return [
            'count' => (int) ($stats->aggregate_count ?? 0),
            'max' => (string) ($stats->aggregate_max ?? ''),
        ];
    };

    $categoryStats = $aggregateVersion(Category::query()->where('is_active', true));
    $subcategoryStats = $aggregateVersion(Subcategory::query()->where('is_active', true));
    $brandStats = $aggregateVersion(Brand::query()->where('is_active', true));
    $portStats = $aggregateVersion(Port::query()->active());

    $filterVersion = md5(json_encode([
        'categories' => $categoryStats,
        'subcategories' => $subcategoryStats,
        'brands' => $brandStats,
        'ports' => $portStats,
    ]));

    return $servicePerformanceCache->remember("services_directory_filter_payload_v3_{$filterVersion}", now()->addMinutes(30), function (): array {
        $categories = Category::query()
            ->with('subcategories:id,category_id,name,slug')
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'has_subcategories']);

        $brands = Brand::query()
            ->where('is_active', true)
            ->get(['id', 'name', 'slug'])
            ->sort(function (Brand $left, Brand $right) {
                $leftName = trim((string) $left->name);
                $rightName = trim((string) $right->name);
                $leftStartsWithLetter = preg_match('/^[A-Za-z]/', $leftName) === 1;
                $rightStartsWithLetter = preg_match('/^[A-Za-z]/', $rightName) === 1;

                if ($leftStartsWithLetter !== $rightStartsWithLetter) {
                    return $leftStartsWithLetter ? -1 : 1;
                }

                return strcasecmp($leftName, $rightName);
            })
            ->values();

        $portIndex = Port::query()
            ->active()
            ->select(['country_code', 'country_name', 'port_name'])
            ->orderBy('country_code')
            ->orderBy('port_name')
            ->get()
            ->groupBy(fn (Port $portModel) => CountryNameResolver::resolve((string) ($portModel->country_code ?: $portModel->country_name)) ?? (string) $portModel->country_name)
            ->map(fn ($ports) => $ports->pluck('port_name')->filter()->unique()->values()->all())
            ->filter(fn (array $ports, string $countryName) => $countryName !== '' && count($ports) > 0)
            ->toArray();

        $countryOptions = collect(CountryNameResolver::all())
            ->values()
            ->filter()
            ->unique()
            ->sort(fn ($left, $right) => strcasecmp((string) $left, (string) $right))
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'brands' => $brands,
            'portIndex' => $portIndex,
            'countryOptions' => $countryOptions,
        ];
    });
};

$getCachedSellerServiceProfileData = static function (User $user) use ($buildServiceCards, $servicePerformanceCache): array {
    $cacheKey = 'service_profile_payload_v3_'.$user->id.'_'.optional($user->updated_at)->timestamp;

    return $servicePerformanceCache->remember($cacheKey, now()->addMinutes(15), function () use ($user, $buildServiceCards): array {
        $categoryIds = collect($user->service_category_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values();
        $subcategoryIds = collect($user->service_subcategories_by_category ?? [])
            ->flatten(1)
            ->merge($user->service_subcategory_ids ?? [])
            ->map(fn ($value) => (int) $value)
            ->filter()
            ->unique()
            ->values();
        $brandIds = collect($user->service_brand_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values();

        $categories = Category::query()
            ->whereIn('id', $categoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $subcategories = Subcategory::query()
            ->whereIn('id', $subcategoryIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'category_id']);

        $brands = Brand::query()
            ->whereIn('id', $brandIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        $vendorServiceCards = $buildServiceCards(
            $user,
            $categories->keyBy('id'),
            $subcategories->keyBy('id'),
        )->values()->all();

        return [
            'categories' => $categories,
            'subcategories' => $subcategories,
            'brands' => $brands,
            'vendorServiceCards' => $vendorServiceCards,
        ];
    });
};

$renderServicesIndex = function (Request $request, ?string $routeCategorySlug = null, ?string $routeSubcategorySlug = null) use ($mapSellerPortsByCountry, $mapListingCard, $getServiceDirectoryFilterPayload) {
    $storageUrl = static fn (?string $path) => $path ? '/storage/'.ltrim($path, '/') : null;
    $normalize = static fn (?string $value) => Str::of((string) $value)
        ->ascii()
        ->lower()
        ->replaceMatches('/[^a-z0-9\s]+/', ' ')
        ->squish()
        ->toString();

    $matchesSearch = static function (array $strings, string $search) use ($normalize): bool {
        $needle = $normalize($search);

        if ($needle === '') {
            return true;
        }

        $haystack = $normalize(implode(' ', array_filter($strings)));

        if ($haystack === '') {
            return false;
        }

        if (str_contains($haystack, $needle)) {
            return true;
        }

        $tokens = array_values(array_filter(explode(' ', $needle)));
        $words = array_values(array_filter(explode(' ', $haystack)));

        foreach ($tokens as $token) {
            $matched = false;

            foreach ($words as $word) {
                if (preg_match('/\d/', $token)) {
                    if ($word === $token) {
                        $matched = true;
                        break;
                    }

                    continue;
                }

                if (str_contains($word, $token)) {
                    $matched = true;
                    break;
                }

                $maxDistance = strlen($token) >= 6 ? 2 : 1;
                if (levenshtein($token, $word) <= $maxDistance) {
                    $matched = true;
                    break;
                }
            }

            if (! $matched) {
                return false;
            }
        }

        return true;
    };

    $filterPayload = $getServiceDirectoryFilterPayload();
    $categories = $filterPayload['categories'];
    $allSubcategories = $categories
        ->flatMap(fn (Category $category) => $category->subcategories)
        ->values();
    $categoriesById = $categories->keyBy('id');
    $subcategoriesById = $allSubcategories->keyBy('id');

    $legacyCategorySlug = trim((string) $request->string('category'));
    $legacySubcategorySlug = trim((string) $request->string('subcategory'));
    $selectedCategorySlugs = collect($request->query('categories', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values();
    $selectedSubcategorySlugs = collect($request->query('subcategories', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values();

    if ($selectedCategorySlugs->isEmpty()) {
        if ($routeCategorySlug !== null) {
            $selectedCategorySlugs = collect([$routeCategorySlug]);
        } elseif ($legacyCategorySlug !== '') {
            $selectedCategorySlugs = collect([$legacyCategorySlug]);
        }
    }

    if ($selectedSubcategorySlugs->isEmpty()) {
        if ($routeSubcategorySlug !== null) {
            $selectedSubcategorySlugs = collect([$routeSubcategorySlug]);
        } elseif ($legacySubcategorySlug !== '') {
            $selectedSubcategorySlugs = collect([$legacySubcategorySlug]);
        }
    }

    $selectedCategories = $categories
        ->filter(fn (Category $category) => $selectedCategorySlugs->contains($category->slug))
        ->values();
    $selectedCategory = $selectedCategories->first();

    if ($routeCategorySlug !== null) {
        abort_unless($selectedCategory, 404);
    }

    $selectedSubcategories = $allSubcategories
        ->filter(fn (Subcategory $subcategory) => $selectedSubcategorySlugs->contains($subcategory->slug))
        ->filter(fn (Subcategory $subcategory) => $selectedCategories->isEmpty() || $selectedCategories->contains('id', $subcategory->category_id))
        ->values();
    $selectedSubcategory = $selectedSubcategories->first();
    $metaSelectedCategory = $selectedCategories->count() === 1 ? $selectedCategory : null;
    $metaSelectedSubcategory = $selectedSubcategories->count() === 1 ? $selectedSubcategory : null;

    if (! $metaSelectedCategory && $metaSelectedSubcategory) {
        $metaSelectedCategory = $categories->firstWhere('id', $metaSelectedSubcategory->category_id);
    }

    if ($routeSubcategorySlug !== null) {
        abort_unless($selectedSubcategory, 404);
    }

    if (($routeCategorySlug !== null || $legacyCategorySlug !== '' || $legacySubcategorySlug !== '')
        && ! $request->has('categories')
        && ! $request->has('subcategories')) {
        $canonicalUrl = ServiceDirectoryRoute::url(
            $selectedCategory,
            $selectedSubcategory,
            $request->except(['category', 'subcategory']),
        );

        if ($request->fullUrl() !== $canonicalUrl) {
            return redirect()->to($canonicalUrl, 301);
        }
    }

    $search = trim((string) $request->string('search'));
    $legacyBrandSlug = trim((string) $request->string('brand'));
    $legacyCountry = trim((string) $request->string('country'));
    $legacyPort = trim((string) $request->string('port'));
    $selectedBrandSlugs = collect($request->query('brands', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values();
    $selectedCountries = collect($request->query('countries', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values();
    $selectedPortTokens = collect($request->query('ports', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values();

    if ($selectedBrandSlugs->isEmpty() && $legacyBrandSlug !== '') {
        $selectedBrandSlugs = collect([$legacyBrandSlug]);
    }

    if ($selectedCountries->isEmpty() && $legacyCountry !== '') {
        $selectedCountries = collect([$legacyCountry]);
    }

    if ($selectedPortTokens->isEmpty() && $legacyPort !== '') {
        $selectedPortTokens = collect([
            $legacyCountry !== '' ? "{$legacyCountry}::{$legacyPort}" : $legacyPort,
        ]);
    }

    $brandOptions = $filterPayload['brands'];
    $selectedBrands = $brandOptions
        ->filter(fn (Brand $item) => $selectedBrandSlugs->contains($item->slug))
        ->values();
    $selectedCountryCodes = $selectedCountries
        ->map(fn (string $countryName) => CountryNameResolver::codeForName($countryName))
        ->filter()
        ->values();
    $selectedPorts = $selectedPortTokens
        ->map(function (string $token) {
            $parts = explode('::', $token, 2);

            if (count($parts) === 2) {
                return [
                    'country' => trim($parts[0]),
                    'port' => trim($parts[1]),
                ];
            }

            return [
                'country' => null,
                'port' => trim($token),
            ];
        })
        ->filter(fn (array $item) => filled($item['port']))
        ->values();
    $portIndex = $filterPayload['portIndex'];
    $countryOptions = $filterPayload['countryOptions'];

    $searchMatchedCategory = null;
    $searchMatchedSubcategory = null;
    if ($search !== '') {
        $normalizedSearch = $normalize($search);

        $searchMatchedCategory = $categories->first(function (Category $category) use ($normalize, $normalizedSearch) {
            $name = $normalize($category->name);
            $slug = $normalize($category->slug);

            return $name !== '' && $normalizedSearch !== '' && (
                str_contains($name, $normalizedSearch)
                || str_contains($normalizedSearch, $name)
                || str_contains($slug, $normalizedSearch)
                || str_contains($normalizedSearch, $slug)
            );
        });

        $searchMatchedSubcategory = $allSubcategories->first(function (Subcategory $subcategory) use ($normalize, $normalizedSearch) {
            $name = $normalize($subcategory->name);
            $slug = $normalize($subcategory->slug);

            return $name !== '' && $normalizedSearch !== '' && (
                str_contains($name, $normalizedSearch)
                || str_contains($normalizedSearch, $name)
                || str_contains($slug, $normalizedSearch)
                || str_contains($normalizedSearch, $slug)
            );
        });

    }

    $page = max(1, (int) $request->integer('page', 1));
    $perPage = 12;
    $normalizedSearch = $search !== '' ? $normalize($search) : '';

    $applyDirectoryFilters = static function ($query) use (
        $normalizedSearch,
        $search,
        $searchMatchedCategory,
        $searchMatchedSubcategory,
        $selectedCategories,
        $selectedSubcategories,
        $selectedBrands,
        $selectedCategory,
        $selectedSubcategory,
        $selectedCountries,
        $selectedCountryCodes,
        $selectedPorts
    ) {
        return $query
            ->when($selectedCategories->isNotEmpty(), fn ($directoryQuery) => $directoryQuery->whereIn('category_id', $selectedCategories->pluck('id')))
            ->when($selectedSubcategories->isNotEmpty(), fn ($directoryQuery) => $directoryQuery->whereIn('subcategory_id', $selectedSubcategories->pluck('id')))
            ->when($selectedBrands->isNotEmpty(), function ($directoryQuery) use ($selectedBrands) {
                $directoryQuery->whereHas('seller', function ($sellerQuery) use ($selectedBrands) {
                    $sellerQuery->where(function ($brandScope) use ($selectedBrands) {
                        foreach ($selectedBrands as $brandItem) {
                            $brandScope->orWhereJsonContains('service_brand_ids', (int) $brandItem->id);
                        }
                    });
                });
            })
            ->when(! $selectedCategory && $searchMatchedCategory, fn ($directoryQuery) => $directoryQuery->where('category_id', $searchMatchedCategory->id))
            ->when(! $selectedSubcategory && ! $selectedCategory && ! $searchMatchedCategory && $searchMatchedSubcategory, fn ($directoryQuery) => $directoryQuery->where('subcategory_id', $searchMatchedSubcategory->id))
            ->when($selectedCountries->isNotEmpty(), function ($directoryQuery) use ($selectedCountries, $selectedCountryCodes) {
                $directoryQuery->where(function ($countryScope) use ($selectedCountries, $selectedCountryCodes) {
                    $countryScope
                        ->whereIn('country', $selectedCountries->all())
                        ->orWhereHas('ports', function ($portsQuery) use ($selectedCountries, $selectedCountryCodes) {
                            $portsQuery->where(function ($countryQuery) use ($selectedCountries, $selectedCountryCodes) {
                                $countryQuery->whereIn('country_name', $selectedCountries->all());

                                if ($selectedCountryCodes->isNotEmpty()) {
                                    $countryQuery->orWhereIn('country_code', $selectedCountryCodes->all());
                                }
                            });
                        });
                });
            })
            ->when($selectedPorts->isNotEmpty(), function ($directoryQuery) use ($selectedPorts) {
                $directoryQuery->whereHas('ports', function ($portsQuery) use ($selectedPorts) {
                    $portsQuery->where(function ($portScope) use ($selectedPorts) {
                        foreach ($selectedPorts as $selectedPort) {
                            $portScope->orWhere(function ($singlePortQuery) use ($selectedPort) {
                                $singlePortQuery
                                    ->where('port_name', $selectedPort['port'])
                                    ->orWhere('unlocode', strtoupper($selectedPort['port']));

                                if (filled($selectedPort['country'])) {
                                    $countryCode = CountryNameResolver::codeForName((string) $selectedPort['country']);
                                    $singlePortQuery->where(function ($countryScope) use ($selectedPort, $countryCode) {
                                        $countryScope->where('country_name', $selectedPort['country']);

                                        if ($countryCode) {
                                            $countryScope->orWhere('country_code', $countryCode);
                                        }
                                    });
                                }
                            });
                        }
                    });
                });
            })
            ->when($search !== '', function ($directoryQuery) use ($normalizedSearch, $search) {
                $companySearchPattern = '%'.implode('%', array_values(array_filter(explode(' ', $normalizedSearch)))).'%';

                $directoryQuery->where(function ($searchScope) use ($normalizedSearch, $search, $companySearchPattern) {
                    $searchScope->where('search_text', 'like', "%{$normalizedSearch}%");

                    if ($companySearchPattern !== '%%') {
                        $searchScope
                            ->orWhereRaw('LOWER(company_name) like ?', [$companySearchPattern])
                            ->orWhereRaw('LOWER(contact_name) like ?', [$companySearchPattern]);
                    }

                    $searchScope->orWhere('company_name', 'like', "%{$search}%");
                });
            });
    };

    $supplierPage = $applyDirectoryFilters(
        SupplierServiceListing::query()
            ->visible()
            ->selectRaw('seller_id, MIN(company_name) as company_name_sort')
            ->groupBy('seller_id')
            ->orderBy('company_name_sort')
    )
        ->paginate($perPage, ['seller_id'], 'page', $page)
        ->appends($request->query());

    $pageSellerIds = collect($supplierPage->items())
        ->map(fn ($item) => (int) $item->seller_id)
        ->filter()
        ->values();

    $representativeListings = $pageSellerIds->isEmpty()
        ? collect()
        : $applyDirectoryFilters(
            SupplierServiceListing::query()
                ->visible()
                ->select([
                    'id',
                    'seller_id',
                    'company_name',
                    'contact_name',
                    'country',
                    'summary',
                    'logo_path',
                    'category_id',
                    'category_name',
                    'category_slug',
                    'subcategory_id',
                    'subcategory_name',
                    'subcategory_slug',
                    'vendor_slug',
                    'search_text',
                ])
                ->with([
                    'seller' => fn ($query) => $query
                        ->select('id')
                        ->withCount('receivedReviews')
                        ->withAvg('receivedReviews as received_reviews_average', 'rating'),
                ])
                ->withCount('ports')
                ->whereIn('seller_id', $pageSellerIds->all())
                ->orderBy('company_name')
                ->orderBy('category_name')
                ->orderBy('subcategory_name')
        )
            ->get()
            ->groupBy('seller_id')
            ->map(fn ($listings) => $listings->first());

    $supplierPage->setCollection(
        $pageSellerIds
            ->map(function (int $sellerId) use ($representativeListings, $mapListingCard, $selectedCountries) {
                $listing = $representativeListings->get($sellerId);

                if (! $listing) {
                    return null;
                }

                return $mapListingCard(
                    $listing,
                    $selectedCountries->count() === 1 ? $selectedCountries->first() : null,
                );
            })
            ->filter()
            ->values()
    );

    $suppliers = $supplierPage;

    $metaTitle = 'Maritime Services Directory | Sea Requests';
    $metaDescription = 'Browse approved supplier services, service countries, ports and company details in the Sea Requests maritime services directory.';
    $metaImage = $request->root().'/'.ltrim(config('brand.assets.og_image', 'brand/sea-requests-og.png'), '/');
    $heroEyebrow = 'Supplier directory';
    $heroTitle = 'Maritime Service Suppliers';
    $heroText = 'Browse approved supplier companies, compare service coverage and open detailed company profiles.';

    if ($metaSelectedCategory && $metaSelectedSubcategory) {
        $metaTitle = "{$metaSelectedSubcategory->name} Services | {$metaSelectedCategory->name} | Sea Requests";
        $metaDescription = "Browse {$metaSelectedSubcategory->name} suppliers under {$metaSelectedCategory->name}, including ports, countries and company details.";
        $heroEyebrow = $metaSelectedCategory->name;
        $heroTitle = "{$metaSelectedSubcategory->name} Suppliers";
        $heroText = "Explore approved {$metaSelectedSubcategory->name} suppliers listed under {$metaSelectedCategory->name}, including countries, ports and company details.";
    } elseif ($metaSelectedCategory) {
        $metaTitle = "{$metaSelectedCategory->name} Services | Sea Requests";
        $metaDescription = "Browse approved suppliers in {$metaSelectedCategory->name}, including ports, countries and company details.";
        $heroEyebrow = 'Service category';
        $heroTitle = "{$metaSelectedCategory->name} Suppliers";
        $heroText = "Browse approved {$metaSelectedCategory->name} suppliers, compare service coverage and open detailed company profiles.";
    }

    return Inertia::render('Service/ServicesIndex', [
        'categories' => $categories
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'has_subcategories' => $category->has_subcategories,
            ])
            ->values(),
        'brands' => $selectedBrands
            ->map(fn (Brand $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
            ])
            ->values(),
        'initialSubcategories' => $selectedSubcategories
            ->map(function (Subcategory $subcategory) use ($categoriesById) {
                $category = $categoriesById->get($subcategory->category_id);

                return [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'slug' => $subcategory->slug,
                    'category_slug' => $category?->slug,
                    'category_name' => $category?->name,
                ];
            })
            ->filter(fn (array $item) => filled($item['category_slug']))
            ->values(),
        'portIndex' => $selectedCountries
            ->mapWithKeys(fn (string $country) => [$country => $portIndex[$country] ?? []])
            ->all(),
        'countryOptions' => $countryOptions,
        'filterDataUrls' => [
            'brands' => route('services.filters.brands'),
            'subcategories' => route('services.filters.subcategories'),
            'ports' => route('services.filters.ports'),
        ],
        'suppliersPage' => $suppliers,
        'filters' => [
            'search' => $search,
            'brands' => $selectedBrands->pluck('slug')->values(),
            'countries' => $selectedCountries->values(),
            'ports' => $selectedPortTokens->values(),
            'parentCategories' => $selectedCategories->pluck('slug')->values(),
            'subcategories' => $selectedSubcategories->pluck('slug')->values(),
        ],
        'meta' => [
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => ServiceDirectoryRoute::url($metaSelectedCategory, $metaSelectedSubcategory, $request->query()),
            'robots' => 'index, follow',
            'ogImage' => $metaImage,
            'twitterCard' => 'summary',
            'heroEyebrow' => $heroEyebrow,
            'heroTitle' => $heroTitle,
            'heroText' => $heroText,
        ],
    ]);
};

Route::get('/services', fn (Request $request) => $renderServicesIndex($request))->name('services.index');

Route::get('/services/filter-options/brands', function () use ($getServiceDirectoryFilterPayload) {
    $filterPayload = $getServiceDirectoryFilterPayload();

    return response()->json([
        'brands' => $filterPayload['brands']
            ->map(fn (Brand $item) => [
                'id' => $item->id,
                'name' => $item->name,
                'slug' => $item->slug,
            ])
            ->values(),
    ]);
})->name('services.filters.brands');

Route::get('/services/filter-options/subcategories', function (Request $request) use ($getServiceDirectoryFilterPayload) {
    $selectedCategorySlugs = collect($request->query('categories', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values();

    if ($selectedCategorySlugs->isEmpty()) {
        return response()->json(['groups' => []]);
    }

    $filterPayload = $getServiceDirectoryFilterPayload();
    $categories = $filterPayload['categories'];

    $groups = $categories
        ->filter(fn (Category $category) => $selectedCategorySlugs->contains($category->slug))
        ->map(fn (Category $category) => [
            'slug' => $category->slug,
            'name' => $category->name,
            'subcategories' => $category->subcategories
                ->sortBy('name')
                ->map(fn (Subcategory $subcategory) => [
                    'id' => $subcategory->id,
                    'name' => $subcategory->name,
                    'slug' => $subcategory->slug,
                    'category_slug' => $category->slug,
                    'category_name' => $category->name,
                ])
                ->values(),
        ])
        ->values();

    return response()->json(['groups' => $groups]);
})->name('services.filters.subcategories');

Route::get('/services/filter-options/ports', function (Request $request) use ($getServiceDirectoryFilterPayload) {
    $selectedCountries = collect($request->query('countries', []))
        ->map(fn ($value) => trim((string) $value))
        ->filter()
        ->values();

    if ($selectedCountries->isEmpty()) {
        return response()->json(['groups' => []]);
    }

    $filterPayload = $getServiceDirectoryFilterPayload();
    $portIndex = $filterPayload['portIndex'];

    $groups = $selectedCountries
        ->map(fn (string $country) => [
            'country' => $country,
            'ports' => collect($portIndex[$country] ?? [])
                ->map(fn (string $port) => [
                    'token' => "{$country}::{$port}",
                    'name' => $port,
                ])
                ->values(),
        ])
        ->values();

    return response()->json(['groups' => $groups]);
})->name('services.filters.ports');

Route::get('/services/{user}', function (Request $request, User $user) {
    abort_unless($user->role === 'seller' && $user->approval_status === 'approved', 404);

    $categoryIds = collect($user->service_category_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values();
    $subcategoryIds = collect($user->service_subcategory_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values();

    $categories = Category::query()->whereIn('id', $categoryIds)->orderBy('name')->get(['id', 'name', 'slug']);
    $subcategories = Subcategory::query()->whereIn('id', $subcategoryIds)->orderBy('name')->get(['id', 'name', 'slug', 'category_id']);

    $selectedCategorySlug = trim((string) $request->string('category'));
    $selectedSubcategorySlug = trim((string) $request->string('subcategory'));
    $selectedCategory = $selectedCategorySlug !== '' ? $categories->firstWhere('slug', $selectedCategorySlug) : null;
    $selectedSubcategory = $selectedSubcategorySlug !== '' ? $subcategories->firstWhere('slug', $selectedSubcategorySlug) : null;

    if ($selectedSubcategory && ! $selectedCategory) {
        $selectedCategory = $categories->firstWhere('id', $selectedSubcategory->category_id);
    }

    if (! $selectedCategory) {
        $selectedCategory = $categories->first();
    }

    abort_unless($selectedCategory, 404);

    $activeSubcategories = $subcategories
        ->filter(fn (Subcategory $subcategory) => (int) $subcategory->category_id === (int) $selectedCategory->id)
        ->values();

    if (! $selectedSubcategory && $activeSubcategories->isNotEmpty()) {
        $selectedSubcategory = $activeSubcategories->first();
    }

    return redirect()->to(ServiceRoute::url($user, $selectedCategory, $selectedSubcategory), 301);
})->whereNumber('user')->name('services.show.legacy');

Route::get('/services/{category:slug}', fn (Request $request, Category $category) => $renderServicesIndex($request, $category->slug))->name('services.category');
Route::get('/services/{category:slug}/{subcategory:slug}', fn (Request $request, Category $category, Subcategory $subcategory) => $renderServicesIndex($request, $category->slug, $subcategory->slug))->name('services.subcategory');

Route::get('/categories', function (Request $request) {
    return redirect()->route('services.index', $request->query(), 301);
})->name('categories.index');

Route::get('/categories/{category:slug}', function (Request $request, Category $category) {
    return redirect()->to(ServiceDirectoryRoute::url($category, null, $request->query()), 301);
})->name('categories.show');

Route::get('/categories/{category:slug}/{subcategory:slug}', function (Request $request, Category $category, Subcategory $subcategory) {
    abort_unless($subcategory->category_id === $category->id, 404);

    return redirect()->to(ServiceDirectoryRoute::url($category, $subcategory, $request->query()), 301);
})->name('subcategories.show');

Route::get('/services/{category}/{subcategory}/{vendor}/reviews-data', function (Request $request, string $category, string $subcategory, string $vendor, SupplierReviewData $reviewData) use ($resolveServiceProfileContext, $buildServiceReviewPayload) {
    $context = $resolveServiceProfileContext($category, $subcategory, $vendor);

    return response()->json(
        $buildServiceReviewPayload($context['user'], $request->user(), $reviewData)
    )->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
})->name('services.show.reviews-data');

Route::get('/services/{category}/{subcategory}/{vendor}/similar-vendors', function (string $category, string $subcategory, string $vendor) use ($resolveServiceProfileContext, $buildSimilarVendorsPayload) {
    $context = $resolveServiceProfileContext($category, $subcategory, $vendor);

    return response()->json([
        'similar_vendors' => $buildSimilarVendorsPayload($context['user'], $context['selectedCategory'])->values(),
    ])->header('X-Robots-Tag', 'noindex, nofollow, noarchive');
})->name('services.show.similar-data');

Route::get('/services/{category}/{subcategory}/{vendor}', function (Request $request, string $category, string $subcategory, string $vendor, SupplierReviewData $reviewData) use ($buildSimilarVendorsPayload, $buildServiceReviewPayload, $getCachedSellerServiceProfileData, $servicePerformanceCache) {
    $userId = ServiceRoute::extractUserId($vendor);
    abort_unless($userId, 404);

    $user = User::query()->select([
        'id',
        'name',
        'email',
        'role',
        'approval_status',
        'updated_at',
        'company_name',
        'country',
        'company_city',
        'company_district',
        'company_neighborhood',
        'company_postal_code',
        'company_address',
        'company_overview',
        'company_description',
        'company_logo_path',
        'contact_email',
        'phone',
        'landline_phone',
        'whatsapp_number',
        'website_url',
        'registration_number',
        'instagram_url',
        'linkedin_url',
        'facebook_url',
        'twitter_url',
        'telegram_url',
        'service_category_ids',
        'service_subcategory_ids',
        'service_subcategories_by_category',
        'service_brand_ids',
        'service_country_codes',
    ])->findOrFail($userId);
    $viewer = $request->user();
    $storageUrl = static fn (?string $path) => $path ? '/storage/'.ltrim($path, '/') : null;

    abort_unless($user->role === 'seller' && $user->approval_status === 'approved', 404);

    $profileData = $getCachedSellerServiceProfileData($user);
    $categories = $profileData['categories'];
    $subcategories = $profileData['subcategories'];
    $brandItems = $profileData['brands'];
    $vendorServiceCards = collect($profileData['vendorServiceCards'] ?? []);
    $matchedListing = SupplierServiceListing::query()
        ->visible()
        ->where('seller_id', $user->id)
        ->where('vendor_slug', $vendor)
        ->where('category_slug', $category)
        ->where(function ($listingQuery) use ($subcategory, $category) {
            $listingQuery->where('subcategory_slug', $subcategory);

            if ($subcategory === $category) {
                $listingQuery->orWhereNull('subcategory_slug');
            }
        })
        ->first(['category_id', 'subcategory_id']);

    $selectedCategory = $categories->firstWhere('slug', $category);
    if (! $selectedCategory && $matchedListing?->category_id) {
        $selectedCategory = $categories->firstWhere('id', (int) $matchedListing->category_id)
            ?? Category::query()->find($matchedListing->category_id, ['id', 'name', 'slug']);
    }
    abort_unless($selectedCategory, 404);

    $activeSubcategories = $subcategories
        ->filter(fn (Subcategory $item) => (int) $item->category_id === (int) $selectedCategory->id)
        ->values();

    if ($matchedListing?->subcategory_id && ! $activeSubcategories->contains(fn (Subcategory $item) => (int) $item->id === (int) $matchedListing->subcategory_id)) {
        $matchedSubcategory = Subcategory::query()->find($matchedListing->subcategory_id, ['id', 'name', 'slug', 'category_id']);

        if ($matchedSubcategory && (int) $matchedSubcategory->category_id === (int) $selectedCategory->id) {
            $activeSubcategories = $activeSubcategories->push($matchedSubcategory)->unique('id')->values();
        }
    }

    $selectedSubcategory = null;
    if ($subcategory !== $selectedCategory->slug) {
        $selectedSubcategory = $activeSubcategories->firstWhere('slug', $subcategory);
        abort_unless($selectedSubcategory, 404);
    } elseif ($activeSubcategories->isNotEmpty()) {
        $selectedSubcategory = $activeSubcategories->first();
    }

    $canonicalUrl = ServiceRoute::url($user, $selectedCategory, $selectedSubcategory);
    $expectedSubcategory = $selectedSubcategory?->slug ?: $selectedCategory->slug;
    if ($vendor !== ServiceRoute::vendorSlug($user) || $category !== $selectedCategory->slug || $subcategory !== $expectedSubcategory) {
        return redirect()->to($canonicalUrl, 301);
    }

    $backUrl = route('services.index');
    $previousUrl = (string) $request->headers->get('referer', '');
    if ($previousUrl !== '') {
        $previousParts = parse_url($previousUrl);
        $previousPath = trim((string) ($previousParts['path'] ?? ''), '/');
        $pathSegments = $previousPath === '' ? [] : explode('/', $previousPath);

        if (($pathSegments[0] ?? null) === 'services' && count($pathSegments) <= 3) {
            $backUrl = $previousUrl;
        }
    }

    $portsByCountry = $servicePerformanceCache->remember('service_profile_ports_by_country_v2_'.$user->id.'_'.optional($user->updated_at)->timestamp, now()->addMinutes(15), function () use ($user): array {
        return $user->servicePorts()
            ->orderBy('country_name')
            ->orderBy('port_name')
            ->get(['ports.id', 'ports.country_code', 'ports.country_name', 'ports.port_name', 'ports.unlocode'])
            ->groupBy('country_code')
            ->map(fn ($ports) => [
                'country_code' => $ports->first()?->country_code,
                'country_name' => CountryNameResolver::resolve((string) ($ports->first()?->country_code ?? $ports->first()?->country_name)),
                'ports' => $ports->map(fn ($port) => [
                    'id' => $port->id,
                    'port_name' => $port->port_name,
                    'unlocode' => $port->unlocode,
                ])->values()->all(),
            ])
            ->values()
            ->all();
    });

    $currentServiceKey = $selectedSubcategory ? "{$user->id}-subcategory-{$selectedSubcategory->id}" : "{$user->id}-category-{$selectedCategory->id}";
    $moreFromVendor = $vendorServiceCards->filter(fn (array $card) => $card['id'] !== $currentServiceKey)->values();

    $pageTitle = $selectedSubcategory?->name ?: $selectedCategory->name;
    $companyName = $user->company_name ?: $user->name;
    $isSellerOwnerView = $viewer?->isSeller() && (int) $viewer->id === (int) $user->id;
    $hasConfirmedBuyerAward = $viewer?->isBuyer()
        ? OfferAward::query()
            ->where('buyer_id', $viewer->id)
            ->where('status', OfferAward::STATUS_CONFIRMED)
            ->whereHas('offer', fn ($query) => $query->where('seller_id', $user->id))
            ->exists()
        : false;
    $canViewDirectContact = $isSellerOwnerView || $hasConfirmedBuyerAward;
    $contactAccessState = ! $viewer
        ? 'guest'
        : ($canViewDirectContact ? 'granted' : 'restricted');
    $initialTab = in_array((string) $request->query('tab'), ['about', 'categories', 'ports', 'business', 'reviews'], true)
        ? (string) $request->query('tab')
        : 'about';
    $initialReviewOfferId = $request->integer('review_offer') ?: null;
    $shouldPreloadReviews = $initialTab === 'reviews' || $initialReviewOfferId !== null;
    $reviewPayload = $shouldPreloadReviews
        ? $buildServiceReviewPayload($user, $viewer, $reviewData)
        : [
            'review_summary' => null,
            'review_items' => [],
            'review_eligibility' => null,
        ];
    $metaImage = $storageUrl($user->company_logo_path)
        ? $request->root().$storageUrl($user->company_logo_path)
        : $request->root().'/'.ltrim(config('brand.assets.og_image', 'brand/sea-requests-og.png'), '/');
    $metaDescription = filled($user->company_overview ?: $user->company_description)
        ? Str::limit(trim(strip_tags((string) ($user->company_overview ?: $user->company_description))), 160)
        : Str::limit("Contact details, service scope and company information for {$companyName} providing {$pageTitle}.", 160);
    $requestServicePath = route('rfqs.create', array_filter([
        'source' => 'supplier_detail',
        'supplier' => $user->id,
        'category_id' => $selectedCategory->id,
        'subcategory_id' => $selectedSubcategory?->id,
        'return_to' => route('services.show', [
            'category' => $selectedCategory->slug,
            'subcategory' => $selectedSubcategory?->slug ?: $selectedCategory->slug,
            'vendor' => ServiceRoute::vendorSlug($user),
        ], false),
    ], fn ($value) => filled($value)), false);
    $requestServiceUrl = $viewer?->isBuyer()
        ? $requestServicePath
        : (! $viewer ? route('login', ['next' => $requestServicePath], false) : null);

    return Inertia::render('Service/ServiceShow', [
        'service' => [
            'id' => $user->id,
            'title' => $pageTitle,
            'summary' => $user->company_description ?: $user->company_overview,
            'overview' => $user->company_overview ?: $user->company_description,
            'logo_url' => $storageUrl($user->company_logo_path),
            'primary_category' => [
                'id' => $selectedCategory->id,
                'name' => $selectedCategory->name,
                'slug' => $selectedCategory->slug,
            ],
            'secondary_category' => $selectedSubcategory ? [
                'id' => $selectedSubcategory->id,
                'name' => $selectedSubcategory->name,
                'slug' => $selectedSubcategory->slug,
            ] : null,
            'categories' => $categories->map(fn (Category $item) => ['id' => $item->id, 'name' => $item->name, 'slug' => $item->slug])->values(),
            'brands' => $brandItems
                ->map(fn (Brand $item) => ['id' => $item->id, 'name' => $item->name, 'slug' => $item->slug])
                ->values(),
            'subcategories' => $subcategories->map(fn (Subcategory $item) => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'name' => $item->name,
                'slug' => $item->slug,
            ])->values(),
            'subcategories_by_category' => collect($user->service_subcategories_by_category ?? [])
                ->mapWithKeys(fn ($subcategoryIds, $categoryId) => [
                    (string) $categoryId => array_values(array_map('intval', $subcategoryIds ?? [])),
                ])
                ->toArray(),
            'vendor' => [
                'name' => $companyName,
                'contact_name' => $canViewDirectContact ? $user->name : null,
                'country' => CountryNameResolver::resolve((string) $user->country),
                'city' => $user->company_city,
                'district' => $user->company_district,
                'neighborhood' => $user->company_neighborhood,
                'postal_code' => $user->company_postal_code,
                'address' => $canViewDirectContact ? $user->company_address : null,
                'email' => $canViewDirectContact ? ($user->contact_email ?: $user->email) : null,
                'phone' => $canViewDirectContact ? $user->phone : null,
                'landline' => $canViewDirectContact ? $user->landline_phone : null,
                'whatsapp' => $canViewDirectContact ? $user->whatsapp_number : null,
                'website' => $canViewDirectContact ? $user->website_url : null,
                'registration_number' => $user->registration_number,
                'instagram' => $canViewDirectContact ? $user->instagram_url : null,
                'linkedin' => $canViewDirectContact ? $user->linkedin_url : null,
                'facebook' => $canViewDirectContact ? $user->facebook_url : null,
                'twitter' => $canViewDirectContact ? $user->twitter_url : null,
                'telegram' => $canViewDirectContact ? $user->telegram_url : null,
                'service_country_codes' => collect($user->service_country_codes ?? [])->filter()->values(),
                'ports_by_country' => $portsByCountry,
            ],
            'more_from_vendor' => $moreFromVendor,
            'similar_vendors' => [],
            'similar_vendors_loaded' => false,
            'similar_vendors_url' => route('services.show.similar-data', [
                'category' => $selectedCategory->slug,
                'subcategory' => $selectedSubcategory?->slug ?: $selectedCategory->slug,
                'vendor' => ServiceRoute::vendorSlug($user),
            ]),
            'can_view_contact_details' => $canViewDirectContact,
            'contact_access_state' => $contactAccessState,
            'back_url' => $backUrl,
            'initial_tab' => $initialTab,
            'initial_review_offer_id' => $initialReviewOfferId,
            'reviews_loaded' => $shouldPreloadReviews,
            'reviews_data_url' => route('services.show.reviews-data', [
                'category' => $selectedCategory->slug,
                'subcategory' => $selectedSubcategory?->slug ?: $selectedCategory->slug,
                'vendor' => ServiceRoute::vendorSlug($user),
            ]),
            'review_summary' => $reviewPayload['review_summary'],
            'review_items' => $reviewPayload['review_items'],
            'review_eligibility' => $reviewPayload['review_eligibility'],
            'request_service_url' => $requestServiceUrl,
        ],
        'meta' => [
            'title' => "{$pageTitle} | {$companyName}",
            'description' => $metaDescription,
            'canonical' => $canonicalUrl,
            'robots' => 'index, follow',
            'ogImage' => $metaImage,
            'twitterCard' => 'summary_large_image',
            'preview' => false,
        ],
    ]);
})->name('services.show');

Route::middleware(['auth'])->get('/admin/services-preview/{category}/{subcategory}/{vendor}', function (Request $request, string $category, string $subcategory, string $vendor) use ($buildServiceCards, $buildCategoryScopedServiceCards) {
    abort_unless($request->user()?->isAdmin(), 403);

    $userId = ServiceRoute::extractUserId($vendor);
    abort_unless($userId, 404);

    $user = User::query()->findOrFail($userId);
    abort_unless($user->role === 'seller', 404);

    $storageUrl = static fn (?string $path) => $path ? '/storage/'.ltrim($path, '/') : null;
    $categoryIds = collect($user->service_category_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values();
    $subcategoryIds = collect($user->service_subcategory_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values();
    $categories = Category::query()->whereIn('id', $categoryIds)->orderBy('name')->get(['id', 'name', 'slug']);
    $subcategories = Subcategory::query()->whereIn('id', $subcategoryIds)->orderBy('name')->get(['id', 'name', 'slug', 'category_id']);

    $selectedCategory = $categories->firstWhere('slug', $category);
    abort_unless($selectedCategory, 404);

    $activeSubcategories = $subcategories
        ->filter(fn (Subcategory $item) => (int) $item->category_id === (int) $selectedCategory->id)
        ->values();

    $selectedSubcategory = null;
    if ($subcategory !== $selectedCategory->slug) {
        $selectedSubcategory = $activeSubcategories->firstWhere('slug', $subcategory);
        abort_unless($selectedSubcategory, 404);
    } elseif ($activeSubcategories->isNotEmpty()) {
        $selectedSubcategory = $activeSubcategories->first();
    }

    $canonicalUrl = ServiceRoute::url($user, $selectedCategory, $selectedSubcategory, 'admin.services.preview');
    $expectedSubcategory = $selectedSubcategory?->slug ?: $selectedCategory->slug;
    if ($vendor !== ServiceRoute::vendorSlug($user) || $category !== $selectedCategory->slug || $subcategory !== $expectedSubcategory) {
        return redirect()->to($canonicalUrl, 301);
    }

    $backUrl = route('services.index');
    $previousUrl = (string) $request->headers->get('referer', '');
    if ($previousUrl !== '') {
        $previousParts = parse_url($previousUrl);
        $previousPath = trim((string) ($previousParts['path'] ?? ''), '/');
        $pathSegments = $previousPath === '' ? [] : explode('/', $previousPath);

        if (($pathSegments[0] ?? null) === 'services' && count($pathSegments) <= 3) {
            $backUrl = $previousUrl;
        }
    }

    $portsByCountry = $user->servicePorts()
        ->orderBy('country_name')
        ->orderBy('port_name')
        ->get(['ports.id', 'ports.country_code', 'ports.country_name', 'ports.port_name', 'ports.unlocode'])
        ->groupBy('country_code')
        ->map(fn ($ports) => [
            'country_code' => $ports->first()?->country_code,
            'country_name' => CountryNameResolver::resolve((string) ($ports->first()?->country_code ?? $ports->first()?->country_name)),
            'ports' => $ports->map(fn ($port) => [
                'id' => $port->id,
                'port_name' => $port->port_name,
                'unlocode' => $port->unlocode,
            ])->values(),
        ])
        ->values();

    $vendorServiceCards = $buildServiceCards($user, $categories->keyBy('id'), $subcategories->keyBy('id'), 'admin.services.preview');
    $currentServiceKey = $selectedSubcategory ? "{$user->id}-subcategory-{$selectedSubcategory->id}" : "{$user->id}-category-{$selectedCategory->id}";
    $moreFromVendor = $vendorServiceCards->filter(fn (array $card) => $card['id'] !== $currentServiceKey)->values();

    $selectedCategorySubcategoriesById = Subcategory::query()
        ->where('category_id', $selectedCategory->id)
        ->orderBy('name')
        ->get(['id', 'name', 'slug', 'category_id'])
        ->keyBy('id');

    $similarVendors = User::query()
        ->select([
            'id',
            'name',
            'company_name',
            'country',
            'company_overview',
            'company_description',
            'company_logo_path',
            'service_category_ids',
            'service_subcategory_ids',
            'service_subcategories_by_category',
        ])
        ->where('role', 'seller')
        ->where('approval_status', 'approved')
        ->where('id', '!=', $user->id)
        ->whereJsonContains('service_category_ids', $selectedCategory->id)
        ->orderByRaw('COALESCE(company_name, name)')
        ->limit(10)
        ->get()
        ->flatMap(fn (User $seller) => $buildCategoryScopedServiceCards($seller, $selectedCategory, $selectedCategorySubcategoriesById, 'admin.services.preview'))
        ->take(10)
        ->values();

    $pageTitle = $selectedSubcategory?->name ?: $selectedCategory->name;
    $companyName = $user->company_name ?: $user->name;
    $metaImage = $storageUrl($user->company_logo_path)
        ? $request->root().$storageUrl($user->company_logo_path)
        : $request->root().'/'.ltrim(config('brand.assets.og_image', 'brand/sea-requests-og.png'), '/');
    $metaDescription = filled($user->company_overview ?: $user->company_description)
        ? Str::limit(trim(strip_tags((string) ($user->company_overview ?: $user->company_description))), 160)
        : Str::limit("Contact details, service scope and company information for {$companyName} providing {$pageTitle}.", 160);

    return Inertia::render('Service/ServiceShow', [
        'service' => [
            'id' => $user->id,
            'title' => $pageTitle,
            'summary' => $user->company_description ?: $user->company_overview,
            'overview' => $user->company_overview ?: $user->company_description,
            'logo_url' => $storageUrl($user->company_logo_path),
            'primary_category' => [
                'id' => $selectedCategory->id,
                'name' => $selectedCategory->name,
                'slug' => $selectedCategory->slug,
            ],
            'secondary_category' => $selectedSubcategory ? [
                'id' => $selectedSubcategory->id,
                'name' => $selectedSubcategory->name,
                'slug' => $selectedSubcategory->slug,
            ] : null,
            'categories' => $categories->map(fn (Category $item) => ['id' => $item->id, 'name' => $item->name, 'slug' => $item->slug])->values(),
            'brands' => Brand::query()
                ->whereIn('id', collect($user->service_brand_ids ?? [])->map(fn ($value) => (int) $value)->filter()->values())
                ->orderBy('name')
                ->get(['id', 'name', 'slug'])
                ->map(fn (Brand $item) => ['id' => $item->id, 'name' => $item->name, 'slug' => $item->slug])
                ->values(),
            'subcategories' => $subcategories->map(fn (Subcategory $item) => [
                'id' => $item->id,
                'category_id' => $item->category_id,
                'name' => $item->name,
                'slug' => $item->slug,
            ])->values(),
            'subcategories_by_category' => collect($user->service_subcategories_by_category ?? [])
                ->mapWithKeys(fn ($subcategoryIds, $categoryId) => [
                    (string) $categoryId => array_values(array_map('intval', $subcategoryIds ?? [])),
                ])
                ->toArray(),
            'vendor' => [
                'name' => $companyName,
                'contact_name' => $user->name,
                'country' => CountryNameResolver::resolve((string) $user->country),
                'city' => $user->company_city,
                'district' => $user->company_district,
                'neighborhood' => $user->company_neighborhood,
                'postal_code' => $user->company_postal_code,
                'address' => $user->company_address,
                'email' => $user->contact_email ?: $user->email,
                'phone' => $user->phone,
                'landline' => $user->landline_phone,
                'whatsapp' => $user->whatsapp_number,
                'website' => $user->website_url,
                'registration_number' => $user->registration_number,
                'instagram' => $user->instagram_url,
                'linkedin' => $user->linkedin_url,
                'facebook' => $user->facebook_url,
                'twitter' => $user->twitter_url,
                'telegram' => $user->telegram_url,
                'service_country_codes' => collect($user->service_country_codes ?? [])->filter()->values(),
                'ports_by_country' => $portsByCountry,
            ],
            'more_from_vendor' => $moreFromVendor,
            'similar_vendors' => $similarVendors,
            'similar_vendors_loaded' => true,
            'similar_vendors_url' => null,
            'can_view_contact_details' => true,
            'contact_access_state' => 'granted',
            'back_url' => $backUrl,
            'initial_tab' => 'about',
            'initial_review_offer_id' => null,
            'reviews_loaded' => true,
            'reviews_data_url' => null,
            'review_summary' => [
                'count' => 0,
                'average' => null,
            ],
            'review_items' => [],
            'review_eligibility' => [
                'access_state' => 'owner',
                'can_submit_review' => false,
                'submit_url' => route('supplier-reviews.store'),
                'targets' => [],
            ],
            'request_service_url' => null,
        ],
        'meta' => [
            'title' => "{$pageTitle} | {$companyName}",
            'description' => $metaDescription,
            'canonical' => ServiceRoute::url($user, $selectedCategory, $selectedSubcategory),
            'robots' => 'noindex, nofollow',
            'ogImage' => $metaImage,
            'twitterCard' => 'summary_large_image',
            'preview' => true,
        ],
    ]);
})->name('admin.services.preview');

$staticPageMeta = static function (string $title, string $description, string $routeName): array {
    return [
        'title' => $title,
        'description' => $description,
        'canonical' => route($routeName),
        'robots' => 'index, follow',
        'ogImage' => asset(config('brand.assets.og_image', 'brand/sea-requests-og.png')),
        'twitterCard' => 'summary_large_image',
    ];
};

Route::get('/terms-of-service', function () use ($staticPageMeta) {
    return Inertia::render('Static/TermsAndConditions', [
        'meta' => $staticPageMeta(
            'Terms & Conditions | Sea Requests',
            'Read the terms governing how buyers and suppliers use Sea Requests and interact through the maritime marketplace.',
            'terms'
        ),
    ]);
})->name('terms');

Route::get('/privacy-policy', function () use ($staticPageMeta) {
    return Inertia::render('Static/PrivacyPolicy', [
        'meta' => $staticPageMeta(
            'Privacy Policy | Sea Requests',
            'Review how Sea Requests handles account, verification, and operational marketplace data across the platform.',
            'privacy'
        ),
    ]);
})->name('privacy');

Route::get('/blog', fn () => Inertia::render('Static/Blog', [
    'meta' => $staticPageMeta(
        'Blog | Sea Requests',
        'Follow Sea Requests marketplace updates, product notes, and operational insights for maritime buyers and suppliers.',
        'blog'
    ),
]))->name('blog');
Route::get('/about-us', fn () => Inertia::render('Static/AboutUs', [
    'meta' => $staticPageMeta(
        'About Us | Sea Requests',
        'Learn how Sea Requests connects maritime buyers and suppliers through RFQs, orders, invoices, payment proof, and direct messaging.',
        'about'
    ),
]))->name('about');
Route::get('/contact', fn () => Inertia::render('Static/Contact', [
    'contactEmail' => 'support@searequests.ai',
    'meta' => $staticPageMeta(
        'Contact | Sea Requests',
        'Contact the Sea Requests team for marketplace questions, buyer and supplier support, or account-related enquiries.',
        'contact'
    ),
]))->name('contact');
Route::post('/contact', [ContactMessageController::class, 'store'])->name('contact.send');
Route::get('/faq', fn () => Inertia::render('Static/Faq', [
    'meta' => $staticPageMeta(
        'FAQ | Sea Requests',
        'Read the most important questions about how Sea Requests works for maritime buyers and suppliers.',
        'faq'
    ),
]))->name('faq');
Route::get('/disclaimer', fn () => Inertia::render('Static/Disclaimer', [
    'meta' => $staticPageMeta(
        'Disclaimer | Sea Requests',
        'Understand the marketplace role of Sea Requests and the commercial responsibilities that remain with buyers and suppliers.',
        'disclaimer'
    ),
]))->name('disclaimer');

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);

    Route::get('/forgot-password', [ForgotPasswordController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'store'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::get('/dashboard/buyer', BuyerDashboardController::class)->name('buyer.dashboard');
    Route::get('/dashboard/buyer/profile', [BuyerProfileController::class, 'edit'])->name('buyer.profile.edit');
    Route::patch('/dashboard/buyer/profile', [BuyerProfileController::class, 'update'])->name('buyer.profile.update');
    Route::get('/dashboard/buyer/requests', BuyerRequestsController::class)->name('buyer.requests');
    Route::get('/dashboard/buyer/orders', BuyerOrdersController::class)->name('buyer.orders');
    Route::get('/dashboard/buyer/orders/{offer}/modal', [BuyerOrdersController::class, 'modal'])->whereNumber('offer')->name('buyer.orders.modal');
    Route::get('/dashboard/buyer/orders/{offer}', [BuyerOrderDetailController::class, 'show'])->whereNumber('offer')->name('buyer.orders.show');
    Route::put('/dashboard/buyer/orders/{offer}/information', [BuyerOrderDetailController::class, 'updateInformation'])->whereNumber('offer')->name('buyer.orders.information.update');
    Route::post('/dashboard/buyer/orders/{offer}/invoices/{invoice}/payment-proof', [BuyerOrderPaymentProofController::class, 'update'])
        ->whereNumber('offer')
        ->whereNumber('invoice')
        ->name('buyer.orders.invoices.payment-proof.update');
    Route::get('/dashboard/buyer/reviews', BuyerReviewsController::class)->name('buyer.reviews');
    Route::get('/dashboard/buyer/requests/{rfq}', [RfqController::class, 'buyerShow'])->whereNumber('rfq')->name('buyer.rfqs.show');
    Route::get('/dashboard/buyer/requests/{rfq}/compare', [RfqController::class, 'buyerCompareShow'])->whereNumber('rfq')->name('buyer.rfqs.compare');
    Route::post('/dashboard/buyer/requests/{rfq}/awards', [RfqController::class, 'buyerAwardsStore'])->whereNumber('rfq')->name('buyer.rfqs.awards.store');
    Route::get('/requests/create', [RfqController::class, 'create'])->name('rfqs.create');
    Route::get('/requests/ports', [RfqController::class, 'ports'])->name('rfqs.ports');
    Route::get('/requests/supplier-matches', [RfqController::class, 'supplierMatches'])->name('rfqs.supplier-matches');
    Route::post('/requests/supplier-suggestions', [RfqController::class, 'supplierSuggestions'])->name('rfqs.supplier-suggestions');
    Route::get('/requests/{rfq}/edit', [RfqController::class, 'edit'])->whereNumber('rfq')->name('rfqs.edit');
    Route::post('/requests/import-preview', [RfqController::class, 'importPreview'])->name('rfqs.import-preview');
    Route::post('/requests/import-template', [RfqController::class, 'saveImportTemplate'])->name('rfqs.import-template');
    Route::post('/requests', [RfqController::class, 'store'])->name('rfqs.store');
    Route::put('/requests/{rfq}', [RfqController::class, 'update'])->whereNumber('rfq')->name('rfqs.update');
    Route::delete('/requests/{rfq}', [RfqController::class, 'destroy'])->whereNumber('rfq')->name('rfqs.destroy');
    Route::middleware('seller.ready')->group(function () {
        Route::get('/dashboard/seller', SellerDashboardController::class)->name('seller.dashboard');
        Route::get('/dashboard/seller/requests', SellerIncomingRequestsController::class)->name('seller.requests');
        Route::get('/dashboard/seller/orders', SellerAwardedRequestsController::class)->name('seller.orders');
        Route::get('/dashboard/seller/requests/{rfq}', [RfqController::class, 'sellerShow'])->whereNumber('rfq')->name('seller.rfqs.show');
        Route::get('/dashboard/seller/orders/{offer}/modal', [SellerAwardedRequestsController::class, 'modal'])->whereNumber('offer')->name('seller.orders.modal');
        Route::get('/dashboard/seller/orders/{offer}', SellerAwardDetailController::class)->whereNumber('offer')->name('seller.orders.show');
        Route::post('/dashboard/seller/orders/{offer}/invoices', [SellerOrderInvoiceController::class, 'store'])
            ->whereNumber('offer')
            ->name('seller.orders.invoices.store');
        Route::post('/dashboard/seller/orders/{offer}/invoices/{invoice}', [SellerOrderInvoiceController::class, 'update'])
            ->whereNumber('offer')
            ->whereNumber('invoice')
            ->name('seller.orders.invoices.update');
        Route::post('/dashboard/seller/orders/{offer}/invoices/{invoice}/payment-confirmation', [SellerOrderPaymentConfirmationController::class, 'store'])
            ->whereNumber('offer')
            ->whereNumber('invoice')
            ->name('seller.orders.invoices.payment-confirm.store');
        Route::redirect('/dashboard/seller/awarded', '/dashboard/seller/orders', 301);
        Route::redirect('/dashboard/seller/awarded/{offer}', '/dashboard/seller/orders/{offer}', 301)->whereNumber('offer');
        Route::get('/dashboard/seller/reviews', SellerReviewsController::class)->name('seller.reviews');
        Route::post('/dashboard/seller/reviews/{review}/reply', [SupplierReviewController::class, 'reply'])->whereNumber('review')->name('seller.reviews.reply');
        Route::delete('/dashboard/seller/reviews/{review}/reply', [SupplierReviewController::class, 'destroyReply'])->whereNumber('review')->name('seller.reviews.reply.destroy');
        Route::get('/dashboard/seller/requests/{rfq}/offer', [RfqController::class, 'sellerOfferCreate'])->whereNumber('rfq')->name('seller.offers.create');
        Route::post('/dashboard/seller/requests/{rfq}/offer', [RfqController::class, 'sellerOfferStore'])->whereNumber('rfq')->name('seller.offers.store');
    });
    Route::post('/supplier-reviews', [SupplierReviewController::class, 'store'])->name('supplier-reviews.store');
    Route::delete('/supplier-reviews/{review}', [SupplierReviewController::class, 'destroy'])->whereNumber('review')->name('supplier-reviews.destroy');
    Route::get('/seller-verification', [SellerVerificationController::class, 'create'])->name('seller.verification.create');
    Route::post('/seller-verification', [SellerVerificationController::class, 'store'])->name('seller.verification.store');
    Route::post('/seller-verification/removal-request', [SellerVerificationController::class, 'requestRemoval'])->name('seller.verification.removal-request');
    Route::get('/approval-pending', ApprovalPendingController::class)->name('approval.pending');

    Route::get('/email/verify', EmailVerificationPromptController::class)->name('verification.notice');
    Route::post('/email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');
    Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::get('/dashboard/admin', AdminDashboardController::class)->name('admin.dashboard');
    Route::get('/dashboard/admin/rfqs', [AdminRfqController::class, 'index'])->name('admin.rfqs');
    Route::get('/dashboard/admin/rfqs/{rfq}/compare', [RfqController::class, 'buyerCompareShow'])->whereNumber('rfq')->name('admin.rfqs.compare');
    Route::post('/dashboard/admin/rfqs/{rfq}/awards', [RfqController::class, 'buyerAwardsStore'])->whereNumber('rfq')->name('admin.rfqs.awards.store');
    Route::get('/dashboard/admin/rfqs/{rfq}/edit', [RfqController::class, 'edit'])->whereNumber('rfq')->name('admin.rfqs.edit');
    Route::put('/dashboard/admin/rfqs/{rfq}', [RfqController::class, 'update'])->whereNumber('rfq')->name('admin.rfqs.update');
    Route::delete('/dashboard/admin/rfqs/{rfq}', [RfqController::class, 'destroy'])->whereNumber('rfq')->name('admin.rfqs.destroy');
    Route::get('/dashboard/admin/rfqs/{rfq}', [AdminRfqController::class, 'show'])->whereNumber('rfq')->name('admin.rfqs.show');
    Route::get('/dashboard/admin/orders', [AdminOrderController::class, 'index'])->name('admin.orders');
    Route::get('/dashboard/admin/orders/{offer}/modal', [AdminOrderController::class, 'modal'])->whereNumber('offer')->name('admin.orders.modal');
    Route::put('/dashboard/admin/orders/{offer}/information', [BuyerOrderDetailController::class, 'updateInformation'])->whereNumber('offer')->name('admin.orders.information.update');
    Route::post('/dashboard/admin/orders/{offer}/invoices', [SellerOrderInvoiceController::class, 'store'])
        ->whereNumber('offer')
        ->name('admin.orders.invoices.store');
    Route::post('/dashboard/admin/orders/{offer}/invoices/{invoice}', [SellerOrderInvoiceController::class, 'update'])
        ->whereNumber('offer')
        ->whereNumber('invoice')
        ->name('admin.orders.invoices.update');
    Route::post('/dashboard/admin/orders/{offer}/invoices/{invoice}/payment-proof', [BuyerOrderPaymentProofController::class, 'update'])
        ->whereNumber('offer')
        ->whereNumber('invoice')
        ->name('admin.orders.invoices.payment-proof.update');
    Route::post('/dashboard/admin/orders/{offer}/invoices/{invoice}/payment-confirmation', [SellerOrderPaymentConfirmationController::class, 'store'])
        ->whereNumber('offer')
        ->whereNumber('invoice')
        ->name('admin.orders.invoices.payment-confirm.store');
    Route::get('/dashboard/admin/orders/{offer}', [AdminOrderController::class, 'show'])->whereNumber('offer')->name('admin.orders.show');
    Route::get('/dashboard/admin/outreach', [AdminOutreachController::class, 'index'])->name('admin.outreach');
    Route::post('/dashboard/admin/outreach/imports', [AdminOutreachController::class, 'import'])->name('admin.outreach.imports.store');
    Route::post('/dashboard/admin/outreach/contacts', [AdminOutreachController::class, 'storeManualContact'])->name('admin.outreach.contacts.store');
    Route::patch('/dashboard/admin/outreach/contacts/{contact}/reactivate', [AdminOutreachController::class, 'reactivateContact'])->name('admin.outreach.contacts.reactivate');
    Route::delete('/dashboard/admin/outreach/contacts/{contact}', [AdminOutreachController::class, 'destroyContact'])->name('admin.outreach.contacts.destroy');
    Route::post('/dashboard/admin/outreach/senders', [AdminOutreachController::class, 'storeSenderAccount'])->name('admin.outreach.senders.store');
    Route::post('/dashboard/admin/outreach/senders/test-draft', [AdminOutreachController::class, 'testDraftSenderAccount'])->name('admin.outreach.senders.test-draft');
    Route::post('/dashboard/admin/outreach/senders/{sender}/test', [AdminOutreachController::class, 'testSenderAccount'])->name('admin.outreach.senders.test');
    Route::put('/dashboard/admin/outreach/senders/{sender}', [AdminOutreachController::class, 'updateSenderAccount'])->name('admin.outreach.senders.update');
    Route::delete('/dashboard/admin/outreach/senders/{sender}', [AdminOutreachController::class, 'destroySenderAccount'])->name('admin.outreach.senders.destroy');
    Route::post('/dashboard/admin/outreach/templates', [AdminOutreachController::class, 'storeTemplate'])->name('admin.outreach.templates.store');
    Route::put('/dashboard/admin/outreach/templates/{template}', [AdminOutreachController::class, 'updateTemplate'])->name('admin.outreach.templates.update');
    Route::delete('/dashboard/admin/outreach/templates/{template}', [AdminOutreachController::class, 'destroyTemplate'])->name('admin.outreach.templates.destroy');
    Route::put('/dashboard/admin/outreach/regions/{regionKey}/plan', [AdminOutreachController::class, 'updateRegionPlan'])->name('admin.outreach.regions.update');
    Route::delete('/dashboard/admin/outreach/regions/{regionKey}/plan', [AdminOutreachController::class, 'destroyRegionPlan'])->name('admin.outreach.regions.destroy');
    Route::get('/admin/vendors', AdminDashboardController::class);
    Route::get('/admin/users/{user}/seller-verification', [SellerVerificationController::class, 'createForAdmin'])->name('admin.seller-verification.edit');
    Route::post('/admin/users/{user}/seller-verification', [SellerVerificationController::class, 'storeForAdmin'])->name('admin.seller-verification.update');
    Route::patch('/admin/users/{user}/approval', [UserApprovalController::class, 'update'])->name('admin.users.approval');
    Route::patch('/admin/users/{user}/profile', [AdminUserManagementController::class, 'updateProfile'])->name('admin.users.profile.update');
    Route::patch('/admin/users/{user}/business', [AdminUserManagementController::class, 'updateBusiness'])->name('admin.users.business.update');
    Route::patch('/admin/users/{user}/removal-request', [AdminUserManagementController::class, 'reviewRemovalRequest'])->name('admin.users.removal-request.review');
    Route::delete('/admin/users/{user}', [AdminUserManagementController::class, 'destroy'])->name('admin.users.delete');
    Route::delete('/admin/users/{user}/business', [AdminUserManagementController::class, 'destroyBusiness'])->name('admin.users.business.delete');
    Route::get('/notifications', NotificationIndexController::class)->name('notifications.index');
    Route::get('/notifications/preview', [NotificationController::class, 'preview'])->name('notifications.preview');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'read'])->name('notifications.read');
    Route::get('/messenger/conversations', [OrderMessengerController::class, 'index'])->name('messenger.conversations.index');
    Route::get('/messenger/conversations/{offer}', [OrderMessengerController::class, 'show'])->whereNumber('offer')->name('messenger.conversations.show');
    Route::post('/messenger/conversations/{offer}/messages', [OrderMessengerController::class, 'store'])->whereNumber('offer')->name('messenger.conversations.messages.store');
    Route::post('/messenger/conversations/{offer}/read', [OrderMessengerController::class, 'markRead'])->whereNumber('offer')->name('messenger.conversations.read');
  });



