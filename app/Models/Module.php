<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Module extends Model {
    protected $fillable = ['title', 'url_slug', 'thumbnail', 'access_tier_id', 'sort_order'];
}