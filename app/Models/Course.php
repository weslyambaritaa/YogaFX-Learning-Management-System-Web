<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Course extends Model {
    protected $fillable = [
        'title', 'url_slug', 'access_tier_id', 'description', 
        'thumbnail', 'video'
    ];
}