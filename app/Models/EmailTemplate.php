<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmailTemplate extends Model {
    protected $fillable = [
        'notification_type', 'notification_name', 'is_enabled', 
        'admin_recipients', 'subject_user', 'body_user', 'subject_admin', 'body_admin'
    ];
}