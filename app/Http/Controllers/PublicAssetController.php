<?php

namespace App\Http\Controllers;

use App\Models\AccessTier;
use App\Models\Package;
use App\Services\BunnyStorageService;
use App\Support\BunnyAssetPath;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicAssetController extends Controller
{
    public function __construct(
        private readonly BunnyStorageService $bunnyStorageService,
    ) {}

    public function show(string $entity, int $id, string $field): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $config = $this->resolveEntityConfig($entity, $field);
        $record = $config['model']::query()->findOrFail($id);
        abort_unless(($config['is_public'])($record), 404);

        $path = $record->{$field};
        abort_unless(filled($path), 404);

        if (BunnyAssetPath::isBunnyPath($path)) {
            $url = $this->bunnyStorageService->url($path);
            abort_unless(filled($url), 404);

            return redirect()->away($url);
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return redirect()->away($path);
        }

        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }

    /**
     * @return array{model: class-string, is_public: \Closure(mixed): bool}
     */
    private function resolveEntityConfig(string $entity, string $field): array
    {
        return match ($entity.':'.$field) {
            'package:image' => [
                'model' => Package::class,
                'is_public' => static fn (Package $package): bool => $package->isCheckoutAvailable(),
            ],
            'access-tier:thumbnail' => [
                'model' => AccessTier::class,
                'is_public' => static fn (AccessTier $accessTier): bool => $accessTier->is_active,
            ],
            default => abort(404),
        };
    }
}
