<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Question extends Model {
    protected $fillable = ['assessment_page_id', 'question_text', 'type', 'sort_order'];
}
