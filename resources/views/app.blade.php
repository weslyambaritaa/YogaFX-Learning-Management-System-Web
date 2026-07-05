<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @php
            $publicBaseUrl = rtrim((string) config('app.public_url', config('app.url', 'http://localhost')), '/');
            $defaultCurrentUrl = $publicBaseUrl.request()->getPathInfo().(request()->getQueryString() ? '?'.request()->getQueryString() : '');
            $meta = array_merge([
                'title' => 'YogaFX',
                'description' => 'YogaFX adalah platform pembelajaran premium dengan pengalaman belajar yoga yang tenang, terpandu, dan content-first.',
                'image' => $publicBaseUrl.'/social-preview-default.svg',
                'url' => $defaultCurrentUrl,
                'type' => 'website',
                'site_name' => 'YogaFX',
                'twitter_card' => 'summary_large_image',
            ], $meta ?? []);
        @endphp

        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ $meta['title'] }}</title>
        <meta name="description" content="{{ $meta['description'] }}">
        <meta property="og:title" content="{{ $meta['title'] }}">
        <meta property="og:description" content="{{ $meta['description'] }}">
        <meta property="og:image" content="{{ $meta['image'] }}">
        <meta property="og:url" content="{{ $meta['url'] }}">
        <meta property="og:type" content="{{ $meta['type'] }}">
        <meta property="og:site_name" content="{{ $meta['site_name'] }}">
        <meta name="twitter:card" content="{{ $meta['twitter_card'] }}">
        <meta name="twitter:title" content="{{ $meta['title'] }}">
        <meta name="twitter:description" content="{{ $meta['description'] }}">
        <meta name="twitter:image" content="{{ $meta['image'] }}">

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">

        <!-- Montserrat: used site-wide (Admin + Student pages) -->
        <link href="https://fonts.bunny.net/css?family=montserrat:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="bg-[#080808] font-sans antialiased" style="font-family: 'Montserrat', sans-serif;">
        @inertia
    </body>
</html>
