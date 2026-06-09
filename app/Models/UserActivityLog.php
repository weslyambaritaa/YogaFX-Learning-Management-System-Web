<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model {
    protected $fillable = [
        'user_id', 'activity_type', 'reference_type', 'reference_id', 
        'description', 'duration_seconds'
    ];
}