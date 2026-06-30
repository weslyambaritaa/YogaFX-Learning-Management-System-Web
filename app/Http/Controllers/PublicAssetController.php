<?php

namespace App\Http\Controllers;

use App\Models\AccessTier;
use App\Models\Package;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PublicAssetController extends Controller
{
    public function show(string $entity, int $id, string $field): Response|\Symfony\Component\HttpFoundation\StreamedResponse
    {
        $config = $this->resolveEntityConfig($entity, $field);
        $record = $config['model']::query()->findOrFail($id);
        abort_unless(($config['is_public'])($record), 404);

        $path = $record->{$field};
        abort_unless($path && ! filter_var($path, FILTER_VALIDATE_URL), 404);
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
