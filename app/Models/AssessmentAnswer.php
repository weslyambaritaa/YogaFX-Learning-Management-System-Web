<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssessmentAnswer extends Model {
    protected $fillable = [
        'assessment_attempt_id', 'question_id', 'question_option_id', 
        'answer_text', 'is_correct', 'is_final', 'answered_at'
    ];
}