<?php

namespace App\Services;

use App\Support\PublicUrl;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class AppDownloadQrService
{
    private const PUBLIC_PATH = '/download-app';

    private const BUNNY_OBJECT_KEY = 'link-control/qr-images/app-download.svg';

    private const LOCAL_PATH = 'link-control/qr-images/app-download.svg';

    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
    ) {}

    public function publicPath(): string
    {
        return self::PUBLIC_PATH;
    }

    public function publicUrl(): string
    {
        return PublicUrl::fromRelativePath($this->publicPath());
    }

    /**
     * @return array{
     *     path: string|null,
     *     warning: string|null,
     *     used_local_fallback: bool
     * }
     */
    public function regenerate(?string $currentPath = null): array
    {
        $svg = $this->buildSvg($this->publicUrl());

        try {
            $path = $this->bunnyStorageService->uploadContents(
                $svg,
                self::BUNNY_OBJECT_KEY,
                'image/svg+xml',
            );

            $this->deleteIfReplaced($currentPath, $path);

            return [
                'path' => $path,
                'warning' => null,
                'used_local_fallback' => false,
            ];
        } catch (Throwable $exception) {
            Log::warning('App download QR Bunny upload failed. Falling back to local storage.', [
                'object_key' => self::BUNNY_OBJECT_KEY,
                'message' => $exception->getMessage(),
            ]);
        }

        try {
            Storage::disk('local')->put(self::LOCAL_PATH, $svg);
            $this->deleteIfReplaced($currentPath, self::LOCAL_PATH);

            return [
                'path' => self::LOCAL_PATH,
                'warning' => 'Store links were saved. QR code switched to local fallback because Bunny upload failed.',
                'used_local_fallback' => true,
            ];
        } catch (Throwable $exception) {
            Log::error('App download QR generation failed completely.', [
                'local_path' => self::LOCAL_PATH,
                'message' => $exception->getMessage(),
            ]);

            return [
                'path' => $currentPath,
                'warning' => 'Store links were saved, but QR code could not be refreshed.',
                'used_local_fallback' => false,
            ];
        }
    }

    private function buildSvg(string $url): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(512, 0),
            new SvgImageBackEnd(),
        );

        return (new Writer($renderer))->writeString($url);
    }

    private function deleteIfReplaced(?string $currentPath, string $newPath): void
    {
        if (! filled($currentPath) || $currentPath === $newPath) {
            return;
        }

        $this->bunnyStorageService->delete($currentPath);
    }
}
