<?php

namespace App\Support;

class UploadConstraints
{
    public const MAX_FILE_SIZE_KB = 10240;
    public const MAX_FILE_SIZE_MB = 10;

    public const LESSON_WORKBOOK_MAX_FILE_SIZE_KB = 102400;
    public const LESSON_WORKBOOK_MAX_FILE_SIZE_MB = 100;

    public const LESSON_AUDIO_MAX_FILE_SIZE_KB = 51200;
    public const LESSON_AUDIO_MAX_FILE_SIZE_MB = 50;

    public const LESSON_AUDIO_SERVER_UPLOAD_MAX_FILE_SIZE_MB = 64;
    public const LESSON_AUDIO_SERVER_POST_MAX_SIZE_MB = 72;

    public static function labelFromMb(int $megabytes): string
    {
        return $megabytes.' MB';
    }
}
