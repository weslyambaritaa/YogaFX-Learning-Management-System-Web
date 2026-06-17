<?php

$normalizeUrl = static function (?string $value, string $default = ''): string {
    $normalized = trim((string) $value);

    if ($normalized === '') {
        $normalized = $default;
    }

    if ($normalized === '') {
        return '';
    }

    if (! preg_match('/^https?:\/\//i', $normalized)) {
        $normalized = 'https://'.$normalized;
    }

    return rtrim($normalized, '/');
};

$storageApiBaseUrl = $normalizeUrl(
    env('BUNNY_STORAGE_API_BASE_URL', env('BUNNY_STORAGE_HOST')),
    'https://storage.bunnycdn.com',
);

$storagePublicBaseUrl = $normalizeUrl(
    env('BUNNY_STORAGE_PUBLIC_BASE_URL', env('BUNNY_CDN_URL')),
);

return [
    'stream' => [
        'api_base_url' => rtrim(env('BUNNY_STREAM_API_BASE_URL', 'https://video.bunnycdn.com'), '/'),
        'library_id' => env('BUNNY_STREAM_LIBRARY_ID'),
        'access_key' => env('BUNNY_STREAM_ACCESS_KEY'),
        'cdn_base_url' => rtrim((string) env('BUNNY_STREAM_CDN_BASE_URL', ''), '/'),
    ],

    'storage' => [
        'api_base_url' => $storageApiBaseUrl,
        'zone_name' => env('BUNNY_STORAGE_ZONE_NAME', env('BUNNY_STORAGE_ZONE')),
        'access_key' => env('BUNNY_STORAGE_ACCESS_KEY', env('BUNNY_STORAGE_PASSWORD')),
        'public_base_url' => $storagePublicBaseUrl,
    ],
];
