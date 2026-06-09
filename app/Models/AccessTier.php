<?php

namespace App\Models;

use Database\Factories\AccessTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'is_active'])]
class AccessTier extends Model
{
    /** @use HasFactory<AccessTierFactory> */
    use HasFactory;

    public const SLUG_STARTER_KIT = 'starter_kit';
    public const SLUG_ONLINE = 'online';
    public const SLUG_MASTER_CLASS = 'master_class';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function ebooks(): HasMany
    {
        return $this->hasMany(Ebook::class);
    }

    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
