<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssessmentAttempt extends Model {
    protected $fillable = [
        'user_id', 'assessment_id', 'attempt_number', 'status', 'score', 
        'is_passed', 'started_at', 'expires_at', 'submitted_at', 'completed_at'
    ];
}