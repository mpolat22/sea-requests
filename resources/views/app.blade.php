<!DOCTYPE html>
<html lang="en">
    <head>
        @php
            $defaultRobotsContent = request()->routeIs('login', 'register', 'password.request', 'password.reset')
                ? 'noindex, nofollow'
                : null;
            $brandName = config('brand.name', config('app.name', 'Sea Requests'));
            $brandDescription = config('brand.description', 'Sea Requests maritime services directory for approved suppliers, countries, ports and company profiles.');
            $themeColor = config('brand.theme_color', '#04151f');
            $pageMeta = is_array($page['props']['meta'] ?? null) ? $page['props']['meta'] : [];
            $pageTitle = $pageMeta['title'] ?? $brandName;
            $pageDescription = $pageMeta['description'] ?? $brandDescription;
            $pageCanonical = $pageMeta['canonical'] ?? url()->current();
            $robotsContent = $pageMeta['robots'] ?? $defaultRobotsContent;
            $pageOgImage = $pageMeta['ogImage'] ?? asset(config('brand.assets.og_image', 'brand/sea-requests-og.png'));
            $twitterCard = $pageMeta['twitterCard'] ?? 'summary_large_image';
            $organizationLogo = asset(config('brand.assets.wordmark_image', 'brand/sea-requests-wordmark.png'));
            $organizationImage = asset(config('brand.assets.mark_image', 'brand/sea-requests-mark.png'));
            $organizationSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => $brandName,
                'url' => url('/'),
                'logo' => $organizationLogo,
                'image' => $organizationImage,
                'description' => $brandDescription,
            ];
        @endphp
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta name="application-name" content="{{ $brandName }}">
        <meta name="description" content="{{ $pageDescription }}">
        @if ($robotsContent)
            <meta name="robots" content="{{ $robotsContent }}">
        @endif
        <meta name="theme-color" content="{{ $themeColor }}">
        <meta property="og:site_name" content="{{ $brandName }}">
        <meta property="og:type" content="website">
        <meta property="og:title" content="{{ $pageTitle }}">
        <meta property="og:description" content="{{ $pageDescription }}">
        <meta property="og:url" content="{{ $pageCanonical }}">
        <meta property="og:image" content="{{ $pageOgImage }}">
        <meta name="twitter:card" content="{{ $twitterCard }}">
        <meta name="twitter:title" content="{{ $pageTitle }}">
        <meta name="twitter:description" content="{{ $pageDescription }}">
        <meta name="twitter:image" content="{{ $pageOgImage }}">
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset(config('brand.assets.favicon_32', 'brand/favicon-32x32.png')) }}">
        <link rel="icon" type="image/png" sizes="16x16" href="{{ asset(config('brand.assets.favicon_16', 'brand/favicon-16x16.png')) }}">
        <link rel="apple-touch-icon" href="{{ asset(config('brand.assets.apple_touch_icon', 'apple-touch-icon.png')) }}">
        <link rel="shortcut icon" href="{{ asset(config('brand.assets.favicon_ico', 'favicon.ico')) }}">
        <link rel="canonical" href="{{ $pageCanonical }}">
        <script type="application/ld+json">{!! json_encode($organizationSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
        <title inertia>{{ $pageTitle }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
