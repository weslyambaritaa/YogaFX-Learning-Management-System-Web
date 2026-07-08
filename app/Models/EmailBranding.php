<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'singleton_key',
    'logo_path',
    'logo_html',
    'header_html',
    'footer_html',
])]
class EmailBranding extends Model
{
    use HasFactory;

    public const GLOBAL_KEY = 'global';
}
