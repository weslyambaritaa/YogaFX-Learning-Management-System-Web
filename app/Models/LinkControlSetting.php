<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'qr_image',
    'google_play_url',
    'app_store_url',
])]
class LinkControlSetting extends Model
{
}
