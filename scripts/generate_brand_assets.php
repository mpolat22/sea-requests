<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$publicPath = $root.DIRECTORY_SEPARATOR.'public';
$brandPath = $publicPath.DIRECTORY_SEPARATOR.'brand';
$masterPath = $brandPath.DIRECTORY_SEPARATOR.'sea-requests-mark-master.png';

if (! is_file($masterPath)) {
    fwrite(STDERR, "Master brand image is missing at public/brand/sea-requests-mark-master.png.\n");
    exit(1);
}

if (! is_dir($brandPath) && ! mkdir($brandPath, 0777, true) && ! is_dir($brandPath)) {
    fwrite(STDERR, "Unable to create brand assets directory.\n");
    exit(1);
}

$palette = [
    'ink' => '#0d1b2a',
    'teal' => '#0f3d4e',
    'foam' => '#67c1b7',
    'white' => '#ffffff',
    'sky' => '#eff5fb',
    'muted' => '#63748a',
    'stroke' => '#dce8f3',
];

$fontRegular = firstExistingFont([
    'C:\\Windows\\Fonts\\segoeui.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
    '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
]);

$fontBold = firstExistingFont([
    'C:\\Windows\\Fonts\\segoeuib.ttf',
    '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
    '/usr/share/fonts/truetype/liberation2/LiberationSans-Bold.ttf',
]);

$master = loadPng($masterPath);

saveResizedPng($master, $brandPath.DIRECTORY_SEPARATOR.'sea-requests-mark.png', 256, 256);
saveResizedPng($master, $brandPath.DIRECTORY_SEPARATOR.'favicon-32x32.png', 32, 32);
saveResizedPng($master, $brandPath.DIRECTORY_SEPARATOR.'favicon-16x16.png', 16, 16);
saveResizedPng($master, $publicPath.DIRECTORY_SEPARATOR.'apple-touch-icon.png', 180, 180);
saveResizedPng($master, $publicPath.DIRECTORY_SEPARATOR.'android-chrome-192x192.png', 192, 192);
saveResizedPng($master, $publicPath.DIRECTORY_SEPARATOR.'android-chrome-512x512.png', 512, 512);

createWordmarkPng(
    $master,
    $brandPath.DIRECTORY_SEPARATOR.'sea-requests-wordmark.png',
    $palette,
    $fontRegular,
    $fontBold
);

createOgPng(
    $master,
    $brandPath.DIRECTORY_SEPARATOR.'sea-requests-og.png',
    $palette,
    $fontRegular,
    $fontBold
);

createIco($publicPath.DIRECTORY_SEPARATOR.'favicon.ico', $brandPath.DIRECTORY_SEPARATOR.'favicon-32x32.png');
imagedestroy($master);

echo "Brand assets generated successfully.\n";

function firstExistingFont(array $candidates): ?string
{
    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

function loadPng(string $path): \GdImage
{
    $image = @imagecreatefrompng($path);

    if (! $image instanceof \GdImage) {
        throw new RuntimeException('Unable to load master PNG image.');
    }

    imagealphablending($image, true);
    imagesavealpha($image, true);

    return $image;
}

function createTransparentCanvas(int $width, int $height): \GdImage
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    imagealphablending($image, true);

    if (function_exists('imageantialias')) {
        imageantialias($image, true);
    }

    return $image;
}

function saveResizedPng(\GdImage $source, string $path, int $width, int $height): void
{
    $canvas = createTransparentCanvas($width, $height);
    imagecopyresampled(
        $canvas,
        $source,
        0,
        0,
        0,
        0,
        $width,
        $height,
        imagesx($source),
        imagesy($source)
    );
    imagepng($canvas, $path, 9);
    imagedestroy($canvas);
}

function hexToColor(\GdImage $image, string $hex, int $alpha = 0): int
{
    $hex = ltrim($hex, '#');

    return imagecolorallocatealpha(
        $image,
        hexdec(substr($hex, 0, 2)),
        hexdec(substr($hex, 2, 2)),
        hexdec(substr($hex, 4, 2)),
        $alpha
    );
}

function createWordmarkPng(
    \GdImage $master,
    string $path,
    array $palette,
    ?string $fontRegular,
    ?string $fontBold
): void {
    $width = 980;
    $height = 240;
    $canvas = createTransparentCanvas($width, $height);
    $ink = hexToColor($canvas, $palette['ink']);
    $muted = hexToColor($canvas, $palette['muted']);

    imagecopyresampled(
        $canvas,
        $master,
        0,
        18,
        0,
        0,
        204,
        204,
        imagesx($master),
        imagesy($master)
    );

    if ($fontBold && $fontRegular) {
        imagettftext($canvas, 62, 0, 236, 108, $ink, $fontBold, 'Sea Requests');
        imagettftext($canvas, 24, 0, 240, 156, $muted, $fontRegular, 'Marine supplier marketplace');
    } else {
        imagestring($canvas, 5, 236, 60, 'Sea Requests', $ink);
        imagestring($canvas, 3, 240, 120, 'Marine supplier marketplace', $muted);
    }

    imagepng($canvas, $path, 9);
    imagedestroy($canvas);
}

function createOgPng(
    \GdImage $master,
    string $path,
    array $palette,
    ?string $fontRegular,
    ?string $fontBold
): void {
    $width = 1200;
    $height = 630;
    $canvas = createTransparentCanvas($width, $height);
    $sky = hexToColor($canvas, $palette['sky']);
    $panel = hexToColor($canvas, $palette['white']);
    $stroke = hexToColor($canvas, $palette['stroke']);
    $ink = hexToColor($canvas, $palette['ink']);
    $muted = hexToColor($canvas, $palette['muted']);
    $foam = hexToColor($canvas, $palette['foam']);

    imagefilledrectangle($canvas, 0, 0, $width, $height, $sky);
    imagefilledrectangle($canvas, 62, 62, $width - 62, $height - 62, $panel);
    imagerectangle($canvas, 62, 62, $width - 63, $height - 63, $stroke);

    imagecopyresampled(
        $canvas,
        $master,
        118,
        128,
        0,
        0,
        184,
        184,
        imagesx($master),
        imagesy($master)
    );

    if ($fontBold && $fontRegular) {
        imagettftext($canvas, 46, 0, 348, 220, $ink, $fontBold, 'Sea Requests');
        imagettftext($canvas, 20, 0, 350, 266, $muted, $fontRegular, 'Marine supplier marketplace');
        imagettftext($canvas, 24, 0, 118, 386, $ink, $fontBold, 'Launch-ready maritime sourcing workflow');
        imagettftext($canvas, 18, 0, 118, 430, $muted, $fontRegular, 'RFQs, supplier discovery, orders, invoices, payment proof, and direct messaging in one system.');
        imagettftext($canvas, 18, 0, 118, 520, $foam, $fontBold, 'Marine procurement made clearer from request to order.');
    } else {
        imagestring($canvas, 5, 348, 180, 'Sea Requests', $ink);
        imagestring($canvas, 3, 350, 220, 'Marine supplier marketplace', $muted);
        imagestring($canvas, 5, 118, 360, 'Launch-ready maritime sourcing workflow', $ink);
        imagestring($canvas, 3, 118, 400, 'RFQs, supplier discovery, orders, invoices, payment proof, and direct messaging in one system.', $muted);
    }

    imagepng($canvas, $path, 9);
    imagedestroy($canvas);
}

function createIco(string $path, string $pngPath): void
{
    $pngData = file_get_contents($pngPath);

    if ($pngData === false) {
        throw new RuntimeException('Unable to read PNG data for favicon.ico generation.');
    }

    $size = @getimagesize($pngPath);
    $width = $size[0] ?? 32;
    $height = $size[1] ?? 32;

    $header = pack('vvv', 0, 1, 1);
    $entry = pack(
        'CCCCvvVV',
        $width >= 256 ? 0 : $width,
        $height >= 256 ? 0 : $height,
        0,
        0,
        1,
        32,
        strlen($pngData),
        6 + 16
    );

    file_put_contents($path, $header.$entry.$pngData);
}
