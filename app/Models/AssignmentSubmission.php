<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssignmentSubmission extends Model {
    protected $fillable = [
        'user_id', 'assignment_type', 'assignment_video', 'assignment_status', 
        'assignment_feedback', 'submitted_at', 'graded_at'
    ];
}