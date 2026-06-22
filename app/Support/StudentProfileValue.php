<?php

namespace App\Support;

use DateTimeInterface;

class StudentProfileValue
{
    private const PRACTICING_YOGA_FOR_MAP = [
        'less_than_1_year' => 'beginner',
        'beginner' => 'beginner',
        '1-3 years' => '0_to_3_years',
        '0 to 3 years' => '0_to_3_years',
        '0_to_3_years' => '0_to_3_years',
        '3-5 years' => '4_to_6_years',
        '4 to 6 years' => '4_to_6_years',
        '4_to_6_years' => '4_to_6_years',
        '5+ years' => '6_plus_years',
        '6+ years' => '6_plus_years',
        '6_plus_years' => '6_plus_years',
    ];

    private const YOGA_SEQUENCE_EXPERIENCE_MAP = [
        'bikram' => 'bikram',
        'hatha' => 'hatha',
        'astanga' => 'astanga',
        'vinyasa' => 'vinyasa',
        'yin' => 'yin',
        'iyengar' => 'iyengar',
        'pilates' => 'pilates',
        'other' => 'other',
        'beginner' => 'other',
        'intermediate' => 'other',
        'advanced' => 'other',
    ];

    private const HOW_DID_YOU_FIND_US_MAP = [
        'google' => 'google',
        'facebook' => 'facebook',
        'instagram' => 'instagram',
        'chatgpt' => 'chatgpt',
        'gemini' => 'gemini',
        'perplexity' => 'perplexity',
        'youtube' => 'youtube',
        'yoga studio' => 'yoga_studio',
        'yoga_studio' => 'yoga_studio',
        'word of mouth' => 'word_of_mouth',
        'word_of_mouth' => 'word_of_mouth',
        'other' => 'other',
    ];

    private const HOURS_PER_WEEK_MAP = [
        '0-3' => '0_3',
        '0_3' => '0_3',
        '03' => '0_3',
        '4-7' => '4_7',
        '4_7' => '4_7',
        '47' => '4_7',
        '7-10' => '7_10',
        '7_10' => '7_10',
        '710' => '7_10',
        '10+' => '10_plus',
        '10_plus' => '10_plus',
        '10plus' => '10_plus',
    ];

    /**
     * @return array<int, string>
     */
    public static function decodeMultiSelect(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                fn (mixed $item): ?string => self::normalizeString($item),
                $value,
            )));
        }

        if (! is_string($value)) {
            return [];
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return [];
        }

        $decoded = json_decode($trimmed, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return self::decodeMultiSelect($decoded);
        }

        return [$trimmed];
    }

    public static function encodeMultiSelect(mixed $value): ?string
    {
        $items = self::decodeMultiSelect($value);

        if ($items === []) {
            return null;
        }

        return json_encode(array_values(array_unique($items)));
    }

    public static function isFilled(mixed $value): bool
    {
        if (is_array($value)) {
            return self::decodeMultiSelect($value) !== [];
        }

        if ($value instanceof DateTimeInterface) {
            return true;
        }

        if (is_bool($value) || is_int($value) || is_float($value)) {
            return true;
        }

        return ! is_null(self::normalizeString($value));
    }

    public static function normalizePracticingYogaFor(mixed $value): ?string
    {
        $normalized = self::normalizeString($value);

        if ($normalized === null) {
            return null;
        }

        return self::PRACTICING_YOGA_FOR_MAP[$normalized] ?? $normalized;
    }

    public static function normalizeHoursPerWeek(mixed $value): ?string
    {
        $normalized = self::normalizeString($value);

        if ($normalized === null) {
            return null;
        }

        return self::HOURS_PER_WEEK_MAP[strtolower($normalized)] ?? $normalized;
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeYogaSequenceExperience(mixed $value): array
    {
        return self::normalizeMappedMultiSelect($value, self::YOGA_SEQUENCE_EXPERIENCE_MAP, 'other');
    }

    /**
     * @return array<int, string>
     */
    public static function normalizeHowDidYouFindUs(mixed $value): array
    {
        return self::normalizeMappedMultiSelect($value, self::HOW_DID_YOU_FIND_US_MAP, 'other');
    }

    /**
     * @param  array<string, string>  $map
     * @return array<int, string>
     */
    private static function normalizeMappedMultiSelect(mixed $value, array $map, ?string $fallback = null): array
    {
        $normalizedItems = collect(self::decodeMultiSelect($value))
            ->map(function (string $item) use ($map, $fallback) {
                $key = strtolower(trim($item));

                return $map[$key] ?? $fallback ?? $item;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $normalizedItems;
    }

    private static function normalizeString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
