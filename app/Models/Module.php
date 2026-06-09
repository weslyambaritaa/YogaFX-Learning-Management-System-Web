<?php

namespace App\Models;

use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'url_slug', 'thumbnail', 'sort_order'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Module $module): void {
            if ($module->sort_order === null) {
                $module->sort_order = ((int) static::query()->max('sort_order')) + 1;
            }
        });
    }

    public function accessTiers(): BelongsToMany
    {
        return $this->belongsToMany(AccessTier::class, 'access_tier_module')->withTimestamps();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }
}
