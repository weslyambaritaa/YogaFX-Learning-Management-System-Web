<?php

namespace App\Models;

use Database\Factories\AccessTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'thumbnail', 'price', 'currency_code', 'level', 'is_active', 'payment_link'])]
class AccessTier extends Model
{
    /** @use HasFactory<AccessTierFactory> */
    use HasFactory;

    public const SLUG_STARTER_KIT = 'starter_kit';
    public const SLUG_ONLINE = 'online';
    public const SLUG_MASTER_CLASS = 'master_class';
    public const CURRENCY_USD = 'USD';
    public const CURRENCY_IDR = 'IDR';
    public const CURRENCY_GBP = 'GBP';
    public const CURRENCY_EUR = 'EUR';

    public const CURRENCY_OPTIONS = [
        self::CURRENCY_IDR,
        self::CURRENCY_USD,
        self::CURRENCY_GBP,
        self::CURRENCY_EUR,
    ];

    public const PUBLIC_PAYMENT_PATHS = [
        self::SLUG_ONLINE => '/online',
        self::SLUG_STARTER_KIT => '/starter-kit',
        self::SLUG_MASTER_CLASS => '/masterclass',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'level' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function packages(): HasMany
    {
        return $this->hasMany(Package::class);
    }

    public function paymentSubscriptions(): HasMany
    {
        return $this->hasMany(PaymentSubscription::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'access_tier_module')->withTimestamps();
    }

    public function lessons(): BelongsToMany
    {
        return $this->belongsToMany(Lesson::class, 'access_tier_lesson')->withTimestamps();
    }

    public function ebooks(): BelongsToMany
    {
        return $this->belongsToMany(Ebook::class, 'access_tier_ebook')->withTimestamps();
    }

    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'access_tier_course')->withTimestamps();
    }

    public function getPriceAmountAttribute(): string
    {
        return (string) $this->price;
    }

    public function setPriceAmountAttribute(mixed $value): void
    {
        $this->attributes['price'] = $value;
    }

    public static function canonicalSlug(string $slug): string
    {
        $normalized = Str::lower(trim($slug));

        return match ($normalized) {
            'starter-kit', 'starter_kit', 'starterkit' => self::SLUG_STARTER_KIT,
            'masterclass', 'master_class', 'master-class' => self::SLUG_MASTER_CLASS,
            'online' => self::SLUG_ONLINE,
            default => Str::slug($normalized, '_'),
        };
    }

    public static function publicPaymentPathForSlug(string $slug): ?string
    {
        return self::PUBLIC_PAYMENT_PATHS[self::canonicalSlug($slug)] ?? null;
    }

    public function resolvedPublicPaymentPath(): ?string
    {
        return self::publicPaymentPathForSlug($this->slug);
    }
}
