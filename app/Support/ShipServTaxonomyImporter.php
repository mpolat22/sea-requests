<?php

namespace App\Support;

use App\Models\Brand;
use App\Models\ShipservBrandImport;
use App\Models\ShipservCategoryImport;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use RuntimeException;

class ShipServTaxonomyImporter
{
    private const BASE_URL = 'https://www.shipserv.com';

    private const CATEGORY_INDEX_URL = 'https://www.shipserv.com/supplier/category';

    private const BRAND_INDEX_URL = 'https://www.shipserv.com/supplier/brand';

    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    public function importCategories(string $batchId, int $maxPages = 80, int $timeout = 20): array
    {
        return $this->crawlDirectory(
            batchId: $batchId,
            indexUrl: self::CATEGORY_INDEX_URL,
            listingPrefix: '/supplier/category',
            entryPattern: '~^https://www\.shipserv\.com/category/([^/?#]+)/(\d+)(?:[/?#].*)?$~i',
            importModel: ShipservCategoryImport::class,
            timeout: $timeout,
            maxPages: $maxPages,
        );
    }

    public function importBrands(string $batchId, int $maxPages = 80, int $timeout = 20, bool $syncBrands = true): array
    {
        $summary = $this->crawlDirectory(
            batchId: $batchId,
            indexUrl: self::BRAND_INDEX_URL,
            listingPrefix: '/supplier/brand',
            entryPattern: '~^https://www\.shipserv\.com/brand/([^/?#]+)/(\d+)(?:[/?#].*)?$~i',
            importModel: ShipservBrandImport::class,
            timeout: $timeout,
            maxPages: $maxPages,
        );

        if ($syncBrands) {
            $summary['brands_synced'] = $this->syncBrandsFromImports();
        }

        return $summary;
    }

    public function syncBrandsFromImports(): int
    {
        $imports = ShipservBrandImport::query()
            ->orderBy('name')
            ->get();

        $synced = 0;

        foreach ($imports as $index => $import) {
            $brand = Brand::query()
                ->where('source', 'shipserv')
                ->where('source_external_id', $import->shipserv_external_id)
                ->first();

            if (! $brand) {
                $brand = Brand::query()->firstOrNew([
                    'slug' => $import->slug,
                ]);
            }

            $brand->fill([
                'name' => $import->name,
                'slug' => $import->slug,
                'is_active' => true,
                'sort_order' => $index + 1,
                'source' => 'shipserv',
                'source_external_id' => $import->shipserv_external_id,
                'source_url' => $import->source_url,
                'metadata' => [
                    'letter' => $import->letter,
                    'is_featured' => $import->is_featured,
                    'source_path' => $import->source_path,
                ],
            ])->save();

            $import->forceFill([
                'brand_id' => $brand->id,
                'mapping_status' => 'imported',
            ])->save();

            $synced++;
        }

        $this->cleanupSyncedBrandDuplicates();

        return $synced;
    }

    /**
     * @param  class-string<ShipservCategoryImport|ShipservBrandImport>  $importModel
     */
    private function crawlDirectory(
        string $batchId,
        string $indexUrl,
        string $listingPrefix,
        string $entryPattern,
        string $importModel,
        int $timeout,
        int $maxPages,
    ): array {
        $visited = [];
        $queue = collect([$indexUrl]);
        $pagesCrawled = 0;
        $entries = collect();

        while ($queue->isNotEmpty() && $pagesCrawled < $maxPages) {
            $currentUrl = (string) $queue->shift();

            if (isset($visited[$currentUrl])) {
                continue;
            }

            $visited[$currentUrl] = true;
            $pagesCrawled++;

            try {
                $html = $this->fetchHtml($currentUrl, $timeout);
            } catch (RuntimeException) {
                continue;
            }

            $anchors = $this->extractAnchors($html, $currentUrl);

            foreach ($anchors as $anchor) {
                $href = $anchor['href'];

                if (preg_match($entryPattern, $href, $matches) === 1) {
                    $resolvedName = $this->resolveEntryName($matches[1], $anchor['text']);

                    $entries->push([
                        'shipserv_external_id' => (int) $matches[2],
                        'name' => $resolvedName,
                        'slug' => Str::slug($resolvedName),
                        'letter' => $this->resolveLetter($resolvedName),
                        'source_url' => $href,
                        'source_path' => parse_url($href, PHP_URL_PATH),
                        'discovered_on' => parse_url($currentUrl, PHP_URL_PATH),
                        'is_featured' => $currentUrl === $indexUrl,
                        'raw_payload' => [
                            'anchor_text' => $anchor['text'],
                            'anchor_title' => $anchor['title'],
                            'source_page' => $currentUrl,
                        ],
                    ]);

                    continue;
                }

                if ($this->isListingLink($href, $listingPrefix) && ! isset($visited[$href]) && ! $queue->contains($href)) {
                    $queue->push($href);
                }
            }
        }

        if ($entries->isEmpty()) {
            throw new RuntimeException("No taxonomy entries were discovered from {$indexUrl}.");
        }

        $dedupedEntries = $entries
            ->filter(fn (array $entry) => $entry['name'] !== '' && $entry['slug'] !== '')
            ->unique('source_url')
            ->values();

        $existingUrls = $importModel::query()
            ->whereIn('source_url', $dedupedEntries->pluck('source_url'))
            ->pluck('source_url')
            ->all();

        $existingUrlMap = array_fill_keys($existingUrls, true);
        $now = now();

        $rows = $dedupedEntries
            ->map(function (array $entry) use ($batchId, $now) {
                return [
                    'import_batch' => $batchId,
                    'shipserv_external_id' => $entry['shipserv_external_id'],
                    'name' => $entry['name'],
                    'slug' => $entry['slug'],
                    'letter' => $entry['letter'],
                    'source_url' => $entry['source_url'],
                    'source_path' => $entry['source_path'],
                    'discovered_on' => $entry['discovered_on'],
                    'is_featured' => $entry['is_featured'],
                    'raw_payload' => json_encode($entry['raw_payload'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'imported_at' => $now,
                    'last_seen_at' => $now,
                    'updated_at' => $now,
                    'created_at' => $now,
                ];
            })
            ->all();

        foreach (array_chunk($rows, 400) as $chunk) {
            $importModel::query()->upsert(
                $chunk,
                ['source_url'],
                [
                    'import_batch',
                    'shipserv_external_id',
                    'name',
                    'slug',
                    'letter',
                    'source_path',
                    'discovered_on',
                    'is_featured',
                    'raw_payload',
                    'imported_at',
                    'last_seen_at',
                    'updated_at',
                ],
            );
        }

        $newEntries = $dedupedEntries
            ->reject(fn (array $entry) => isset($existingUrlMap[$entry['source_url']]))
            ->count();

        return [
            'batch_id' => $batchId,
            'pages_crawled' => $pagesCrawled,
            'entries_found' => $entries->count(),
            'entries_persisted' => $dedupedEntries->count(),
            'entries_new' => $newEntries,
            'entries_updated' => $dedupedEntries->count() - $newEntries,
        ];
    }

    private function fetchHtml(string $url, int $timeout): string
    {
        try {
            $response = $this->http
                ->accept('text/html,application/xhtml+xml')
                ->withHeaders([
                    'User-Agent' => 'SeaRequestsTaxonomyImporter/1.0 (+https://www.searequests.com)',
                ])
                ->timeout($timeout)
                ->retry(2, 400, null, false)
                ->get($url);
        } catch (RequestException $exception) {
            throw new RuntimeException("Unable to fetch {$url}: {$exception->getMessage()}", previous: $exception);
        }

        if (! $response->successful()) {
            throw new RuntimeException("Unable to fetch {$url}: HTTP {$response->status()}");
        }

        return (string) $response->body();
    }

    /**
     * @return array<int, array{href:string,text:string,title:string}>
     */
    private function extractAnchors(string $html, string $baseUrl): array
    {
        $dom = new \DOMDocument();
        $html = mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($html);
        libxml_clear_errors();

        if (! $loaded) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $nodes = $xpath->query('//a[@href]');

        if ($nodes === false) {
            return [];
        }

        $anchors = [];

        foreach ($nodes as $node) {
            $href = trim((string) $node->getAttribute('href'));

            if ($href === '' || str_starts_with($href, 'javascript:') || str_starts_with($href, 'mailto:') || str_starts_with($href, 'tel:')) {
                continue;
            }

            $absoluteUrl = $this->resolveUrl($baseUrl, $href);

            if ($absoluteUrl === null || ! str_starts_with($absoluteUrl, self::BASE_URL)) {
                continue;
            }

            $anchors[] = [
                'href' => $absoluteUrl,
                'text' => $this->normalizeName($node->textContent ?? ''),
                'title' => $this->normalizeName((string) $node->getAttribute('title')),
            ];
        }

        return $anchors;
    }

    private function resolveUrl(string $baseUrl, string $href): ?string
    {
        try {
            return (string) UriResolver::resolve(new Uri($baseUrl), new Uri($href));
        } catch (\Throwable) {
            return null;
        }
    }

    private function isListingLink(string $url, string $listingPrefix): bool
    {
        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return false;
        }

        if (! str_starts_with($path, $listingPrefix)) {
            return false;
        }

        if ($path !== $listingPrefix && ! str_starts_with($path, $listingPrefix.'/l/')) {
            return false;
        }

        return str_starts_with($url, self::BASE_URL);
    }

    private function resolveLetter(string $value): ?string
    {
        $first = Str::upper(Str::substr(Str::ascii($value), 0, 1));

        if ($first === '') {
            return null;
        }

        return preg_match('/[A-Z]/', $first) === 1 ? $first : '#';
    }

    private function resolveEntryName(string $slug, string $anchorText): string
    {
        $anchor = preg_replace('/\s+/', ' ', $this->normalizeName($anchorText)) ?? '';

        if ($anchor !== '') {
            foreach ([
                '/^Marine suppliers? of\s+/i',
                '/^Marine supplier of\s+/i',
                '/^Supplier of\s+/i',
                '/^Brand owner\s+/i',
            ] as $pattern) {
                $cleaned = preg_replace($pattern, '', $anchor);

                if (is_string($cleaned) && $cleaned !== $anchor) {
                    return trim($cleaned);
                }
            }

            if (Str::startsWith($anchor, 'Marine ')) {
                return trim(Str::after($anchor, 'Marine '));
            }

            return $anchor;
        }

        return $this->normalizeName(
            Str::of($slug)
                ->replace('-', ' ')
                ->title()
                ->value()
        );
    }

    private function cleanupSyncedBrandDuplicates(): void
    {
        $brands = Brand::query()
            ->where('source', 'shipserv')
            ->whereNotNull('source_external_id')
            ->orderBy('id')
            ->get();

        foreach ($brands->groupBy('source_external_id') as $group) {
            $canonical = $group->sortBy('id')->first();
            $duplicates = $group->filter(fn (Brand $brand) => $brand->id !== $canonical?->id);

            if (! $canonical || $duplicates->isEmpty()) {
                continue;
            }

            ShipservBrandImport::query()
                ->whereIn('brand_id', $duplicates->pluck('id'))
                ->update([
                    'brand_id' => $canonical->id,
                    'mapping_status' => 'imported',
                ]);

            Brand::query()
                ->whereIn('id', $duplicates->pluck('id'))
                ->delete();
        }
    }

    private function normalizeName(?string $value): string
    {
        return trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
