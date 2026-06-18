<?php

namespace App\Http\Controllers\Mobile\V1;

use App\Http\Controllers\Controller;
use App\Models\DialogContent;
use App\Support\MobileApiResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DialogController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const DIALOGS = [
        DialogContent::KEY_FULL_STANDING => 'Full Standing Series Dialogue',
        DialogContent::KEY_FULL_FLOOR => 'Full Floor Series Dialogue',
    ];

    /**
     * @var array<string, string>
     */
    private const ROUTE_KEY_MAP = [
        'full-standing' => DialogContent::KEY_FULL_STANDING,
        'full-floor' => DialogContent::KEY_FULL_FLOOR,
    ];

    public function index(Request $request)
    {
        $dialogs = DialogContent::query()
            ->whereIn('key', array_keys(self::DIALOGS))
            ->get()
            ->keyBy('key');

        $items = collect(self::DIALOGS)
            ->map(fn (string $fallbackTitle, string $key): array => $this->dialogPayload(
                $key,
                $fallbackTitle,
                $dialogs->get($key),
            ))
            ->values()
            ->all();

        return MobileApiResponse::success([
            'items' => $items,
        ], 'Mobile dialogs retrieved successfully.');
    }

    public function show(Request $request, string $key)
    {
        $key = self::ROUTE_KEY_MAP[$key] ?? $key;
        $fallbackTitle = self::DIALOGS[$key] ?? null;

        if (! $fallbackTitle) {
            return MobileApiResponse::error(
                'Dialog not found for the authenticated student.',
                Response::HTTP_NOT_FOUND,
            );
        }

        $dialog = DialogContent::query()->where('key', $key)->first();

        return MobileApiResponse::success(
            $this->dialogPayload($key, $fallbackTitle, $dialog),
            'Mobile dialog retrieved successfully.',
        );
    }

    private function dialogPayload(string $key, string $fallbackTitle, ?DialogContent $dialog): array
    {
        return [
            'key' => $key,
            'title' => $dialog?->title ?? $fallbackTitle,
            'content' => $dialog?->content ?? '',
            'has_content' => filled($dialog?->content),
        ];
    }
}
