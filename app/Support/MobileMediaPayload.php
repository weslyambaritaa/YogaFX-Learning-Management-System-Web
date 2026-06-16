<?php

namespace App\Support;

class MobileMediaPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function stream(
        ?string $openUrl,
        ?string $downloadUrl = null,
        ?string $fileName = null,
        ?string $mimeType = null,
        bool $isAvailable = false,
    ): array {
        return [
            'kind' => 'stream',
            'is_available' => $isAvailable,
            'open_url' => $openUrl,
            'download_url' => $downloadUrl,
            'preview_url' => null,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'preview_supported' => false,
            'preview_message' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function file(
        ?string $openUrl,
        ?string $downloadUrl,
        ?string $previewUrl,
        ?string $fileName,
        ?string $mimeType,
        bool $previewSupported,
        ?string $previewMessage,
        bool $isAvailable = false,
    ): array {
        return [
            'kind' => 'file',
            'is_available' => $isAvailable,
            'open_url' => $openUrl,
            'download_url' => $downloadUrl,
            'preview_url' => $previewUrl,
            'file_name' => $fileName,
            'mime_type' => $mimeType,
            'preview_supported' => $previewSupported,
            'preview_message' => $previewMessage,
        ];
    }
}
