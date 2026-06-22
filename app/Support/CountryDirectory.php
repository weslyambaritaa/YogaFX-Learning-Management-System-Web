<?php

namespace App\Support;

class CountryDirectory
{
    private const FLAG_MAP = [
        'Argentina' => '🇦🇷',
        'Australia' => '🇦🇺',
        'Austria' => '🇦🇹',
        'Belgium' => '🇧🇪',
        'Brazil' => '🇧🇷',
        'Canada' => '🇨🇦',
        'China' => '🇨🇳',
        'Denmark' => '🇩🇰',
        'Egypt' => '🇪🇬',
        'Finland' => '🇫🇮',
        'France' => '🇫🇷',
        'Germany' => '🇩🇪',
        'Hong Kong' => '🇭🇰',
        'India' => '🇮🇳',
        'Indonesia' => '🇮🇩',
        'Ireland' => '🇮🇪',
        'Italy' => '🇮🇹',
        'Japan' => '🇯🇵',
        'Malaysia' => '🇲🇾',
        'Mexico' => '🇲🇽',
        'Netherlands' => '🇳🇱',
        'New Zealand' => '🇳🇿',
        'Norway' => '🇳🇴',
        'Philippines' => '🇵🇭',
        'Portugal' => '🇵🇹',
        'Qatar' => '🇶🇦',
        'Saudi Arabia' => '🇸🇦',
        'Singapore' => '🇸🇬',
        'South Africa' => '🇿🇦',
        'South Korea' => '🇰🇷',
        'Spain' => '🇪🇸',
        'Sweden' => '🇸🇪',
        'Switzerland' => '🇨🇭',
        'Taiwan' => '🇹🇼',
        'Thailand' => '🇹🇭',
        'Turkey' => '🇹🇷',
        'United Arab Emirates' => '🇦🇪',
        'United Kingdom' => '🇬🇧',
        'United States' => '🇺🇸',
        'Vietnam' => '🇻🇳',
    ];

    /**
     * @return array<int, array{value: string, label: string, dial_code: string}>
     */
    public static function countries(): array
    {
        return [
            ['value' => 'Argentina', 'label' => 'Argentina', 'dial_code' => '+54'],
            ['value' => 'Australia', 'label' => 'Australia', 'dial_code' => '+61'],
            ['value' => 'Austria', 'label' => 'Austria', 'dial_code' => '+43'],
            ['value' => 'Belgium', 'label' => 'Belgium', 'dial_code' => '+32'],
            ['value' => 'Brazil', 'label' => 'Brazil', 'dial_code' => '+55'],
            ['value' => 'Canada', 'label' => 'Canada', 'dial_code' => '+1'],
            ['value' => 'China', 'label' => 'China', 'dial_code' => '+86'],
            ['value' => 'Denmark', 'label' => 'Denmark', 'dial_code' => '+45'],
            ['value' => 'Egypt', 'label' => 'Egypt', 'dial_code' => '+20'],
            ['value' => 'Finland', 'label' => 'Finland', 'dial_code' => '+358'],
            ['value' => 'France', 'label' => 'France', 'dial_code' => '+33'],
            ['value' => 'Germany', 'label' => 'Germany', 'dial_code' => '+49'],
            ['value' => 'Hong Kong', 'label' => 'Hong Kong', 'dial_code' => '+852'],
            ['value' => 'India', 'label' => 'India', 'dial_code' => '+91'],
            ['value' => 'Indonesia', 'label' => 'Indonesia', 'dial_code' => '+62'],
            ['value' => 'Ireland', 'label' => 'Ireland', 'dial_code' => '+353'],
            ['value' => 'Italy', 'label' => 'Italy', 'dial_code' => '+39'],
            ['value' => 'Japan', 'label' => 'Japan', 'dial_code' => '+81'],
            ['value' => 'Malaysia', 'label' => 'Malaysia', 'dial_code' => '+60'],
            ['value' => 'Mexico', 'label' => 'Mexico', 'dial_code' => '+52'],
            ['value' => 'Netherlands', 'label' => 'Netherlands', 'dial_code' => '+31'],
            ['value' => 'New Zealand', 'label' => 'New Zealand', 'dial_code' => '+64'],
            ['value' => 'Norway', 'label' => 'Norway', 'dial_code' => '+47'],
            ['value' => 'Philippines', 'label' => 'Philippines', 'dial_code' => '+63'],
            ['value' => 'Portugal', 'label' => 'Portugal', 'dial_code' => '+351'],
            ['value' => 'Qatar', 'label' => 'Qatar', 'dial_code' => '+974'],
            ['value' => 'Saudi Arabia', 'label' => 'Saudi Arabia', 'dial_code' => '+966'],
            ['value' => 'Singapore', 'label' => 'Singapore', 'dial_code' => '+65'],
            ['value' => 'South Africa', 'label' => 'South Africa', 'dial_code' => '+27'],
            ['value' => 'South Korea', 'label' => 'South Korea', 'dial_code' => '+82'],
            ['value' => 'Spain', 'label' => 'Spain', 'dial_code' => '+34'],
            ['value' => 'Sweden', 'label' => 'Sweden', 'dial_code' => '+46'],
            ['value' => 'Switzerland', 'label' => 'Switzerland', 'dial_code' => '+41'],
            ['value' => 'Taiwan', 'label' => 'Taiwan', 'dial_code' => '+886'],
            ['value' => 'Thailand', 'label' => 'Thailand', 'dial_code' => '+66'],
            ['value' => 'Turkey', 'label' => 'Turkey', 'dial_code' => '+90'],
            ['value' => 'United Arab Emirates', 'label' => 'United Arab Emirates', 'dial_code' => '+971'],
            ['value' => 'United Kingdom', 'label' => 'United Kingdom', 'dial_code' => '+44'],
            ['value' => 'United States', 'label' => 'United States', 'dial_code' => '+1'],
            ['value' => 'Vietnam', 'label' => 'Vietnam', 'dial_code' => '+84'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function countryOptions(): array
    {
        return array_map(
            fn (array $country): array => [
                'value' => $country['value'],
                'label' => $country['label'],
                'flag' => self::FLAG_MAP[$country['value']] ?? '🌍',
            ],
            self::countries(),
        );
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function phoneCountryCodeOptions(): array
    {
        return array_values(array_map(
            fn (array $country): array => [
                'value' => $country['dial_code'],
                'label' => $country['label'].' ('.$country['dial_code'].')',
                'flag' => self::FLAG_MAP[$country['value']] ?? '🌍',
            ],
            self::countries(),
        ));
    }

    public static function dialCodeForCountry(?string $country): string
    {
        foreach (self::countries() as $entry) {
            if ($entry['value'] === $country) {
                return $entry['dial_code'];
            }
        }

        return '+62';
    }

    /**
     * @return array{country_code: string, local_number: string}
     */
    public static function splitPhoneNumber(?string $phoneNumber, ?string $country = null): array
    {
        $fallbackCode = self::dialCodeForCountry($country);
        $phoneNumber = trim((string) $phoneNumber);

        if ($phoneNumber === '') {
            return [
                'country_code' => $fallbackCode,
                'local_number' => '',
            ];
        }

        if (preg_match('/^(\+\d+)\s*(.*)$/', $phoneNumber, $matches) === 1) {
            return [
                'country_code' => $matches[1],
                'local_number' => trim($matches[2]),
            ];
        }

        return [
            'country_code' => $fallbackCode,
            'local_number' => $phoneNumber,
        ];
    }

    public static function formatPhoneNumber(string $countryCode, string $localNumber): string
    {
        $countryCode = trim($countryCode);
        $localNumber = preg_replace('/\s+/', ' ', trim($localNumber)) ?? '';

        return trim($countryCode.' '.$localNumber);
    }
}
