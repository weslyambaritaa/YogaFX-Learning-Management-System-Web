<?php

namespace App\Models;

use App\Models\Concerns\MaintainsSequentialSortOrder;
use Database\Factories\ModuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['title', 'description', 'url_slug', 'thumbnail', 'sort_order'])]
class Module extends Model
{
    /** @use HasFactory<ModuleFactory> */
    use HasFactory;
    use MaintainsSequentialSortOrder;

    public function accessTiers(): BelongsToMany
    {
        return $this->belongsToMany(AccessTier::class, 'access_tier_module')->withTimestamps();
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }
}
