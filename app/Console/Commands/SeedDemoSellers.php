<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Port;
use App\Models\Subcategory;
use App\Models\User;
use App\Support\CountryNameResolver;
use App\Support\SupplierServiceListingIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SeedDemoSellers extends Command
{
    private const DEMO_PASSWORD = 'Rr220915Mm+7';

    private const DEMO_EMAILS = [
        'enverasli732@gmail.com',
        'ahmetyalova39@gmail.com',
        'ahuyilmaz297@gmail.com',
        'kurt.abdullah.fb@gmail.com',
        'bahar.kayacik543@gmail.com',
        'arslannihat8885@gmail.com',
        'melisefe.besiktas@gmail.com',
        'aydincanberk13@gmail.com',
        'lemansaglam1@gmail.com',
        'avciercan913@gmail.com',
    ];

    protected $signature = 'demo:seed-sellers {--count=10 : Number of approved demo sellers to create} {--fresh : Remove existing demo sellers before reseeding}';

    protected $description = 'Create fully approved demo seller accounts with fixed country, port, category, and media coverage for visual testing.';

    public function handle(): int
    {
        $count = max(1, (int) $this->option('count'));

        if ($this->option('fresh')) {
            $this->deleteExistingDemoSellers();
        }

        $categories = Category::query()
            ->with(['subcategories:id,category_id,name,slug'])
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        if ($categories->count() < 15) {
            $this->error('At least 15 active categories are required to create the requested demo seller groups.');

            return self::FAILURE;
        }

        $countries = Port::query()
            ->active()
            ->select('country_code')
            ->distinct()
            ->orderBy('country_code')
            ->get()
            ->pluck('country_code')
            ->filter()
            ->take(12)
            ->values();

        if ($countries->count() < 12) {
            $this->error('At least 12 active countries with ports are required to create the requested demo sellers.');

            return self::FAILURE;
        }

        $portsByCountryCode = Port::query()
            ->active()
            ->whereIn('country_code', $countries->all())
            ->orderBy('country_code')
            ->orderBy('port_name')
            ->get(['id', 'country_code', 'country_name', 'port_name'])
            ->groupBy('country_code');

        if ($portsByCountryCode->isEmpty()) {
            $this->error('No active ports were found for the selected 12 countries.');

            return self::FAILURE;
        }

        $countryGroups = [
            $countries->slice(0, 4)->values(),
            $countries->slice(4, 4)->values(),
            $countries->slice(8, 4)->values(),
        ];

        $sourceAssets = $this->resolveSourceAssets();

        if ($sourceAssets === null) {
            $this->error('Could not find the source seller media set from ahmetcan.mail2026@gmail.com.');

            return self::FAILURE;
        }

        $categoryGroups = [
            $categories->slice(0, 5)->values(),
            $categories->slice(5, 5)->values(),
            $categories->slice(10, 5)->values(),
        ];

        $created = collect();

        for ($index = 0; $index < $count; $index++) {
            $number = $index + 1;
            $groupIndex = $this->categoryGroupIndexForPosition($number);
            $selectedCategories = $categoryGroups[$groupIndex];
            $selectedCountryCodes = $countryGroups[$groupIndex] ?? collect();
            $selectedCountryNames = $selectedCountryCodes
                ->map(fn (string $code) => CountryNameResolver::resolve($code) ?? $code)
                ->values();
            $selectedPortIds = $this->selectedPortIdsForSeller($number, $selectedCountryCodes, $portsByCountryCode);
            $selectedSubcategoriesByCategory = $selectedCategories
                ->values()
                ->mapWithKeys(fn (Category $category, int $categoryIndex) => [
                    (string) $category->id => $this->selectedSubcategoriesForCategory($category, $number, $categoryIndex),
                ]);

            $selectedSubcategories = $selectedSubcategoriesByCategory
                ->flatMap(fn (Collection $subcategories) => $subcategories)
                ->unique('id')
                ->values();

            $subcategoriesByCategory = $selectedSubcategoriesByCategory
                ->map(fn (Collection $subcategories) => $subcategories
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->values()
                    ->all())
                ->all();

            $email = self::DEMO_EMAILS[$index] ?? sprintf('demo-seller-%02d@example.test', $number);
            $companyName = 'Demo Supplier -'.$number;
            $contactName = 'Demo Supplier -'.$number;

            $user = User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $contactName,
                    'locale' => 'en',
                    'password' => Hash::make(self::DEMO_PASSWORD),
                    'role' => 'seller',
                    'company_name' => $companyName,
                    'phone' => sprintf('+90 532 100 %02d %02d', $number, $number),
                    'country' => $selectedCountryNames->first(),
                    'countries' => $selectedCountryNames->implode(', '),
                    'whatsapp_number' => sprintf('+90 532 100 %02d %02d', $number, $number),
                    'company_description' => $this->summaryForSeller($number, $selectedCategories),
                    'company_overview' => $this->overviewForSeller($number, $selectedCategories, $selectedCountryNames),
                    'company_address' => 'Demo Maritime Plaza, Port Operations Floor, '.$selectedCountryNames->first(),
                    'company_address_line' => 'Demo Maritime Plaza, Port Operations Floor',
                    'company_city' => 'Demo Port City',
                    'company_district' => 'Harbor District',
                    'company_neighborhood' => 'Marina Quarter',
                    'company_postal_code' => '34000',
                    'registration_number' => 'DEMO-SUP-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'website_url' => 'https://demo-supplier-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).'.example.test',
                    'landline_phone' => sprintf('+90 212 500 %02d %02d', $number, $number),
                    'contact_email' => $email,
                    'instagram_url' => 'https://instagram.com/demo_supplier_'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'linkedin_url' => 'https://linkedin.com/company/demo-supplier-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'facebook_url' => 'https://facebook.com/demo-supplier-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'twitter_url' => 'https://x.com/demo_supplier_'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'telegram_url' => 'https://t.me/demo_supplier_'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'service_category_ids' => $selectedCategories->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'service_subcategory_ids' => $selectedSubcategories->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
                    'service_subcategories_by_category' => $subcategoriesByCategory,
                    'service_country_codes' => $selectedCountryCodes->all(),
                    'seller_verification_submitted_at' => now(),
                    'approval_status' => 'approved',
                    'approved_at' => now(),
                    'email_verified_at' => now(),
                ]
            );

            $media = $this->copyMediaSet($user->id, $sourceAssets);

            $user->forceFill([
                'email_verified_at' => now(),
                'company_logo_path' => $media['logo'],
                'company_cover_path' => null,
                'company_gallery' => [],
                'company_registration_documents' => $media['company_registration_documents'],
                'tax_certificate_documents' => $media['tax_certificate_documents'],
                'service_authorization_documents' => $media['service_authorization_documents'],
                'company_registration_document_path' => $media['company_registration_documents'][0]['path'] ?? null,
                'tax_certificate_document_path' => $media['tax_certificate_documents'][0]['path'] ?? null,
                'service_authorization_document_path' => $media['service_authorization_documents'][0]['path'] ?? null,
            ])->save();

            $user->servicePorts()->sync($selectedPortIds);
            app(SupplierServiceListingIndex::class)->syncSeller($user);

            $created->push([
                'id' => $user->id,
                'company' => $user->company_name,
                'email' => $user->email,
                'password' => self::DEMO_PASSWORD,
                'categories' => $selectedCategories->count(),
                'subcategories' => $selectedSubcategories->count(),
                'countries' => $selectedCountryCodes->count(),
                'ports' => count($selectedPortIds),
            ]);
        }

        $this->table(['ID', 'Company', 'Email', 'Password', 'Categories', 'Subcategories', 'Countries', 'Ports'], $created->all());
        $this->info($created->count().' fully approved demo seller accounts are ready.');

        return self::SUCCESS;
    }

    private function categoryGroupIndexForPosition(int $position): int
    {
        if ($position <= 4) {
            return 0;
        }

        if ($position <= 7) {
            return 1;
        }

        return 2;
    }

    private function summaryForSeller(int $number, Collection $categories): string
    {
        $focus = $categories->pluck('name')->take(3)->implode(', ');

        return 'Demo Supplier -'.$number.' is prepared for marketplace testing with curated category, country, and port coverage focused on '.$focus.'.';
    }

    private function overviewForSeller(int $number, Collection $categories, Collection $countryNames): string
    {
        $categoryList = $categories->pluck('name')->implode(', ');
        $countryList = $countryNames->implode(', ');

        return 'Demo Supplier -'.$number.' is a fully approved test supplier profile created for platform QA. '
            .'This account covers a mixed working-port selection across its assigned country group and is enabled across the following categories: '
            .$categoryList.'. Countries covered: '.$countryList.'.';
    }

    private function selectedSubcategoriesForCategory(Category $category, int $position, int $categoryIndex): Collection
    {
        $subcategories = $category->subcategories
            ->sortBy('name')
            ->values();

        $total = $subcategories->count();

        if ($total <= 1) {
            return $subcategories;
        }

        $take = $this->demoSubcategorySelectionCount($total);
        $offset = max(0, $position - 1) + $categoryIndex;
        $step = max(1, (int) floor($total / max(1, $take)));
        $selected = collect();
        $usedIndexes = [];

        for ($index = 0; $index < ($total * 2) && $selected->count() < $take; $index++) {
            $candidateIndex = ($offset + ($index * $step)) % $total;

            if (isset($usedIndexes[$candidateIndex])) {
                continue;
            }

            $selected->push($subcategories[$candidateIndex]);
            $usedIndexes[$candidateIndex] = true;
        }

        for ($index = 0; $index < $total && $selected->count() < $take; $index++) {
            if (isset($usedIndexes[$index])) {
                continue;
            }

            $selected->push($subcategories[$index]);
            $usedIndexes[$index] = true;
        }

        return $selected
            ->unique('id')
            ->values();
    }

    private function demoSubcategorySelectionCount(int $total): int
    {
        if ($total <= 1) {
            return $total;
        }

        if ($total <= 3) {
            return 1;
        }

        if ($total <= 6) {
            return 2;
        }

        if ($total <= 12) {
            return 3;
        }

        return min($total - 1, max(4, min(12, (int) ceil($total * 0.12))));
    }

    private function selectedPortIdsForSeller(int $position, Collection $countryCodes, Collection $portsByCountryCode): array
    {
        $seed = max(0, $position - 1);

        return $countryCodes
            ->flatMap(function (string $countryCode) use ($portsByCountryCode, $seed) {
                /** @var \Illuminate\Support\Collection<int, \App\Models\Port> $ports */
                $ports = ($portsByCountryCode->get($countryCode) ?? collect())
                    ->sortBy('port_name')
                    ->values();

                $total = $ports->count();

                if ($total === 0) {
                    return [];
                }

                if ($total === 1) {
                    return [$ports->first()->id];
                }

                $take = min($total, max(1, (int) ceil($total * 0.55)));

                if ($total > 3) {
                    $take = min($take, $total - 1);
                }

                $offset = $seed % $total;
                $selected = collect();

                for ($index = 0; $index < $take; $index++) {
                    $selected->push($ports[($offset + ($index * 2)) % $total]->id);
                }

                return $selected->unique()->values()->all();
            })
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function deleteExistingDemoSellers(): void
    {
        $demoUsers = User::query()
            ->where(function ($query) {
                $query->where('email', 'like', 'demo-seller-%@example.test')
                    ->orWhere(function ($nested) {
                        $nested->where('company_name', 'like', 'Demo Supplier -%')
                            ->where('email', '!=', self::DEMO_EMAILS[0]);
                    });
            })
            ->get();

        foreach ($demoUsers as $user) {
            $user->servicePorts()->detach();
            Storage::disk('public')->deleteDirectory('seller-verifications/'.$user->id);
            $user->delete();
        }
    }

    private function resolveSourceAssets(): ?array
    {
        $sourceUser = User::query()->where('email', self::DEMO_EMAILS[0])->first();

        if (! $sourceUser || ! $sourceUser->company_logo_path) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($sourceUser->company_logo_path)) {
            return null;
        }

        $companyRegistrationDocuments = collect($sourceUser->company_registration_documents ?? [])
            ->pluck('path')
            ->filter(fn ($path) => $disk->exists($path))
            ->take(1)
            ->values()
            ->all();

        $taxCertificateDocuments = collect($sourceUser->tax_certificate_documents ?? [])
            ->pluck('path')
            ->filter(fn ($path) => $disk->exists($path))
            ->take(1)
            ->values()
            ->all();

        $serviceAuthorizationDocuments = collect($sourceUser->service_authorization_documents ?? [])
            ->pluck('path')
            ->filter(fn ($path) => $disk->exists($path))
            ->take(1)
            ->values()
            ->all();

        return [
            'logo' => $sourceUser->company_logo_path,
            'company_registration_documents' => $companyRegistrationDocuments,
            'tax_certificate_documents' => $taxCertificateDocuments,
            'service_authorization_documents' => $serviceAuthorizationDocuments,
        ];
    }

    private function copyMediaSet(int $userId, array $sourceAssets): array
    {
        $disk = Storage::disk('public');
        $baseDirectory = 'seller-verifications/'.$userId;

        $copySingle = function (string $sourcePath, string $targetDirectory) use ($disk): string {
            $extension = pathinfo($sourcePath, PATHINFO_EXTENSION);
            $targetPath = trim($targetDirectory, '/').'/'.Str::uuid().($extension ? '.'.$extension : '');
            $disk->copy($sourcePath, $targetPath);

            return $targetPath;
        };

        $copyDocuments = function (array $paths, string $targetDirectory) use ($copySingle): array {
            return collect($paths)
                ->map(fn ($path) => [
                    'path' => $copySingle($path, $targetDirectory),
                    'name' => basename($path),
                ])
                ->values()
                ->all();
        };

        return [
            'logo' => $copySingle($sourceAssets['logo'], $baseDirectory.'/logo'),
            'company_registration_documents' => $copyDocuments($sourceAssets['company_registration_documents'], $baseDirectory.'/company-registration'),
            'tax_certificate_documents' => $copyDocuments($sourceAssets['tax_certificate_documents'], $baseDirectory.'/tax-certificates'),
            'service_authorization_documents' => $copyDocuments($sourceAssets['service_authorization_documents'], $baseDirectory.'/service-authorizations'),
        ];
    }
}
