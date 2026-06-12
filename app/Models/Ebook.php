<?php

namespace App\Models;

use App\Models\Concerns\MaintainsSequentialSortOrder;
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
    use MaintainsSequentialSortOrder;

    public function accessTiers(): BelongsToMany
    {
        return $this->belongsToMany(AccessTier::class, 'access_tier_ebook')->withTimestamps();
    }
}
