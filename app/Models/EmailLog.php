<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class EmailLog extends Model {
    protected $fillable = [
        'email_template_id', 'notification_type', 'reference_type', 
        'reference_id', 'recipient_type', 'recipient_email', 'subject', 
        'body_snapshot', 'status', 'error_message', 'sent_at'
    ];
}