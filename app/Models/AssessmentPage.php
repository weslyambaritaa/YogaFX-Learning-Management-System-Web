<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AssessmentPage extends Model {
    protected $fillable = ['assessment_id', 'title', 'sort_order'];
}