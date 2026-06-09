<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class QuestionOption extends Model {
    protected $fillable = ['question_id', 'text_option', 'is_true', 'sort_order'];
}