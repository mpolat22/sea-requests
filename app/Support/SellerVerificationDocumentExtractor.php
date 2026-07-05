<?php

namespace App\Support;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class SellerVerificationDocumentExtractor
{
    /**
     * @param  array<int, array<string, mixed>>  $documents
     * @return array<int, array<string, mixed>>
     */
    public function extract(array $documents): array
    {
        return collect($documents)
            ->filter(fn ($document) => filled($document['path'] ?? null))
            ->map(fn ($document) => $this->extractSingleDocument($document))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>|null
     */
    private function extractSingleDocument(array $document): ?array
    {
        $path = (string) ($document['path'] ?? '');

        if ($path === '' || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $absolutePath = Storage::disk('public')->path($path);
        $mimeType = $this->detectMimeType($absolutePath, $document['mime_type'] ?? null);
        $sha256 = $document['sha256'] ?? $this->sha256($absolutePath);
        $size = isset($document['size']) ? (int) $document['size'] : (@filesize($absolutePath) ?: null);
        $name = (string) ($document['name'] ?? basename($path));

        $base = [
            'path' => $path,
            'name' => $name,
            'mime_type' => $mimeType,
            'size' => $size,
            'sha256' => $sha256,
            'page_images' => [],
            'ocr_lines' => [],
        ];

        if ($mimeType === 'application/pdf') {
            $pdf = $this->extractPdfData($absolutePath, $name);

            return [
                ...$base,
                'source_type' => 'pdf',
                'page_images' => $pdf['page_images'],
                'ocr_lines' => $pdf['ocr_lines'],
            ];
        }

        if (is_string($mimeType) && str_starts_with($mimeType, 'image/')) {
            return [
                ...$base,
                'source_type' => 'image',
                'page_images' => [$this->fileToDataUrl($absolutePath, $mimeType)],
            ];
        }

        return [
            ...$base,
            'source_type' => 'unknown',
        ];
    }

    private function detectMimeType(string $absolutePath, mixed $fallback = null): ?string
    {
        if (is_string($fallback) && trim($fallback) !== '') {
            return trim($fallback);
        }

        try {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($absolutePath);

            return is_string($mime) && $mime !== '' ? $mime : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function sha256(string $absolutePath): ?string
    {
        try {
            $hash = @hash_file('sha256', $absolutePath);

            return is_string($hash) && $hash !== '' ? $hash : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{ocr_lines: array<int, string>, page_images: array<int, string>}
     */
    private function extractPdfData(string $absolutePath, string $name): array
    {
        $python = $this->resolvePythonExecutable();
        $script = base_path('scripts/extract_pdf_rows.py');

        if (! is_file($script)) {
            return [
                'ocr_lines' => [],
                'page_images' => [],
            ];
        }

        $process = new Process([$python, $script, $absolutePath]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            Log::warning('Seller verification PDF extraction failed.', [
                'file_name' => $name,
                'exit_code' => $process->getExitCode(),
                'stdout' => trim($process->getOutput()),
                'stderr' => trim($process->getErrorOutput()),
            ]);

            return [
                'ocr_lines' => [],
                'page_images' => [],
            ];
        }

        $decoded = json_decode($process->getOutput(), true);

        if (! is_array($decoded)) {
            return [
                'ocr_lines' => [],
                'page_images' => [],
            ];
        }

        return [
            'ocr_lines' => collect($decoded['ocr_lines'] ?? [])
                ->filter(fn ($line) => is_string($line) && trim($line) !== '')
                ->take(160)
                ->values()
                ->all(),
            'page_images' => collect($decoded['page_images'] ?? [])
                ->filter(fn ($image) => is_string($image) && str_starts_with($image, 'data:image/'))
                ->take(5)
                ->values()
                ->all(),
        ];
    }

    private function resolvePythonExecutable(): string
    {
        $finder = new ExecutableFinder;

        $candidates = array_values(array_filter([
            env('RFQ_PDF_PYTHON'),
            env('PYTHON_EXECUTABLE'),
            'C:\\Users\\rmust\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe',
            'python3',
            'python',
        ]));

        foreach ($candidates as $candidate) {
            $candidate = (string) $candidate;

            if ($candidate === '') {
                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }

            $resolved = $finder->find($candidate);

            if (is_string($resolved) && $resolved !== '') {
                return $resolved;
            }
        }

        return 'python';
    }

    private function fileToDataUrl(string $absolutePath, string $mimeType): ?string
    {
        $bytes = @file_get_contents($absolutePath);

        if (! is_string($bytes) || $bytes === '') {
            return null;
        }

        return 'data:'.$mimeType.';base64,'.base64_encode($bytes);
    }
}
