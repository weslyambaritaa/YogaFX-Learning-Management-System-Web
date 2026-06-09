<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class LessonProgress extends Model {
    protected $fillable = [
        'user_id', 'lesson_id', 'watch_progress', 'is_workbook_downloaded', 
        'workbook_downloaded_at', 'video_completed_at', 'is_done', 'completed_at'
    ];
}