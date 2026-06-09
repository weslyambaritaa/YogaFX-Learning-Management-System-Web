<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class UserSession extends Model {
    protected $fillable = [
        'user_id', 'login_at', 'logout_at', 'last_activity_at', 
        'session_duration_seconds', 'is_active'
    ];
}