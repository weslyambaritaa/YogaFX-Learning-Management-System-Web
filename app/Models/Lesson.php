<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Lesson extends Model {
    protected $fillable = [
        'module_id', 'access_tier_id', 'assessment_id', 'title', 
        'thumbnail', 'workbook', 'video', 'audio', 'content', 'sort_order'
    ];
}