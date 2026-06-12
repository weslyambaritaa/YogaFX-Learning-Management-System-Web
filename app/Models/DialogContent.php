<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'key',
    'title',
    'content',
])]
class DialogContent extends Model
{
    public const KEY_FULL_STANDING = 'full_standing';

    public const KEY_FULL_FLOOR = 'full_floor';
}
