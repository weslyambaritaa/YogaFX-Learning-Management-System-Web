<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'support_whatsapp',
    'support_email',
])]
class SupportSetting extends Model
{
}
