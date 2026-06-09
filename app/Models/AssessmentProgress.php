<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssessmentProgress extends Model {
    protected $fillable = [
        'user_id', 'assessment_id', 'latest_score', 'highest_score', 
        'total_attempts', 'is_done', 'completed_at'
    ];
}