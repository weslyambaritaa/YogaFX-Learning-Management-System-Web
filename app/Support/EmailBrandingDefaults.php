<?php

namespace App\Support;

class EmailBrandingDefaults
{
    public static function logoHtml(): string
    {
        $appName = e((string) config('app.name', 'YogaFX LMS'));

        return '<p style="margin: 0; font-size: 18px; font-weight: 600; color: #0f172a;">'.$appName.'</p>';
    }

    public static function headerHtml(): string
    {
        $appName = e((string) config('app.name', 'YogaFX LMS'));

        return '<p style="margin: 0; font-size: 18px; font-weight: 600; color: #0f172a;">'.$appName.'</p>';
    }

    public static function footerHtml(): string
    {
        $appName = e((string) config('app.name', 'YogaFX LMS'));
        $year = now()->format('Y');

        return '<p style="margin: 0; font-size: 13px; color: #475569;">'.$appName.' &bull; '.$year.'</p>';
    }
}
