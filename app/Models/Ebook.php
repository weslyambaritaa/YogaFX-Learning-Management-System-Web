<?php

namespace App\Models;

use Database\Factories\EbookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['title', 'file', 'sort_order'])]
class Ebook extends Model
{
    /** @use HasFactory<EbookFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Ebook $ebook): void {
            if ($ebook->sort_order === null) {
                $ebook->sort_order = ((int) static::query()->max('sort_order')) + 1;
            }
        });
    }

    public function accessTiers(): BelongsToMany
    {
        return $this->belongsToMany(AccessTier::class, 'access_tier_ebook')->withTimestamps();
    }
}
