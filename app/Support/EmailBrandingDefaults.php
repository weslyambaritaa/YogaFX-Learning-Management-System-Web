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

    public static function pdfHeaderHtml(): string
    {
        $appName = e((string) config('app.name', 'YogaFX LMS'));

        return '<div style="text-align:center;"><p style="margin:0;font-size:24px;font-weight:800;letter-spacing:0.02em;color:#111827;">'.$appName.'</p></div>';
    }

    public static function watermarkHtml(): string
    {
        return '<div style="font-size:520px;line-height:0.78;color:rgba(220, 38, 38, 0.12);font-weight:900;">Y</div>';
    }
}
