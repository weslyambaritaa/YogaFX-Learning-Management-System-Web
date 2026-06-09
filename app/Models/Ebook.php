<?php

namespace App\Models;

use Database\Factories\EbookFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['title', 'file', 'access_tier_id'])]
class Ebook extends Model
{
    /** @use HasFactory<EbookFactory> */
    use HasFactory;

    public function accessTier(): BelongsTo
    {
        return $this->belongsTo(AccessTier::class);
    }
}
