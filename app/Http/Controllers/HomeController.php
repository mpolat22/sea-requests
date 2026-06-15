<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Rfq;
use App\Models\Subcategory;
use App\Models\SupplierServiceListing;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function __invoke(): Response
    {
        $requestLimit = 6;
        $supplierLimit = 8;

        $latestRequests = Cache::remember("home:latest-requests:v3:{$requestLimit}", now()->addMinutes(2), function () use ($requestLimit) {
            return Rfq::query()
                ->published()
                ->publicMarketplace()
                ->withCount('items')
                ->latest('updated_at')
                ->limit($requestLimit)
                ->get()
                ->map(fn (Rfq $rfq) => $this->mapHomeRequest($rfq))
                ->values();
        });

        $featuredSuppliers = Cache::remember("home:featured-suppliers:v3:{$supplierLimit}", now()->addMinutes(10), function () use ($supplierLimit) {
            return SupplierServiceListing::query()
                ->visible()
                ->orderBy('company_name')
                ->orderBy('category_name')
                ->get([
                    'seller_id',
                    'company_name',
                    'country',
                    'summary',
                    'category_name',
                    'category_slug',
                    'subcategory_name',
                    'subcategory_slug',
                    'vendor_slug',
                ])
                ->unique('seller_id')
                ->take($supplierLimit)
                ->map(function (SupplierServiceListing $listing) {
                    return [
                        'id' => $listing->seller_id,
                        'name' => $listing->company_name,
                        'company_name' => $listing->company_name,
                        'summary' => $listing->summary,
                        'display_country' => $listing->country,
                        'primary_category' => [
                            'name' => $listing->category_name,
                            'slug' => $listing->category_slug,
                        ],
                        'secondary_category' => [
                            'name' => $listing->subcategory_name,
                            'slug' => $listing->subcategory_slug,
                        ],
                        'href' => route('services.show', [
                            'category' => $listing->category_slug,
                            'subcategory' => $listing->subcategory_slug ?: $listing->category_slug,
                            'vendor' => $listing->vendor_slug,
                        ]),
                    ];
                })
                ->values();
        });

        $heroStats = Cache::remember('home:hero-stats:v3', now()->addMinutes(5), function () {
            return [
                [
                    'key' => 'sellers',
                    'label' => 'Suppliers',
                    'value' => SupplierServiceListing::query()
                        ->visible()
                        ->distinct('seller_id')
                        ->count('seller_id'),
                ],
                [
                    'key' => 'buyers',
                    'label' => 'Buyers',
                    'value' => User::query()->where('role', 'buyer')->count(),
                ],
                [
                    'key' => 'rfqs',
                    'label' => "RFQ's",
                    'value' => Rfq::query()->published()->publicMarketplace()->count(),
                ],
                [
                    'key' => 'categories',
                    'label' => 'Categories',
                    'value' => Category::query()->where('is_active', true)->count() + Subcategory::query()->count(),
                ],
            ];
        });

        return Inertia::render('Home', [
            'meta' => [
                'title' => 'Sea Requests | Marine supplier marketplace',
                'description' => config('brand.description'),
                'canonical' => route('home'),
                'robots' => 'index, follow',
                'ogImage' => asset(config('brand.assets.og_image', 'brand/sea-requests-og.png')),
                'twitterCard' => 'summary_large_image',
            ],
            'hero' => [
                'stats' => $heroStats,
                'latest_requests' => $latestRequests,
                'requests_url' => route('requests.index'),
                'register_url' => route('register'),
            ],
            'featured_requests' => $latestRequests,
            'featured_suppliers' => $featuredSuppliers,
            'home_links' => [
                'requests_url' => route('requests.index'),
                'services_url' => route('services.index'),
                'register_url' => route('register'),
            ],
        ]);
    }

    private function mapHomeRequest(Rfq $rfq): array
    {
        $itemCount = (int) ($rfq->items_count ?: $rfq->items_count_count ?: 0);
        $companySeed = trim((string) ($rfq->company_name ?: $rfq->reference_no ?: 'REQ'));
        $companyMask = mb_substr($companySeed, 0, 3) . '***';
        $countryNames = collect($rfq->country_names ?? [])
            ->filter()
            ->values();

        return [
            'id' => $rfq->id,
            'reference_no' => $rfq->reference_no,
            'request_type' => $rfq->request_type,
            'company_mask' => $companyMask,
            'items_count' => $itemCount,
            'service_title' => $rfq->service_title,
            'service_description' => $rfq->service_description,
            'country_names' => $countryNames->all(),
            'country_summary' => $this->summarizeCountries($countryNames, (string) $rfq->country_name),
            'status' => $rfq->canReceiveSupplierResponses() ? 'live' : 'close',
            'updated_at' => optional($rfq->updated_at)?->toISOString(),
            'submitted_at' => optional($rfq->submitted_at)?->toISOString(),
            'show_url' => $rfq->publicShowUrl(),
        ];
    }

    private function summarizeCountries(Collection $countryNames, string $fallback): string
    {
        if ($countryNames->isNotEmpty()) {
            return $countryNames->implode(', ');
        }

        return $fallback !== '' ? $fallback : '-';
    }
}
