<?php

use App\Models\AccessTier;
use App\Models\Certificate;

return [
    'output_directory' => 'certificates',
    'template_output_directory' => 'certificates/templates',
    'font_families' => [
        'dejavu_sans' => base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf'),
    ],
    'tiers' => [
        AccessTier::SLUG_STARTER_KIT => [
            Certificate::TYPE_BIKRAM,
        ],
        AccessTier::SLUG_ONLINE => [
            Certificate::TYPE_BIKRAM,
            Certificate::TYPE_YOGA_ALLIANCE,
        ],
        AccessTier::SLUG_MASTER_CLASS => [
            Certificate::TYPE_BIKRAM,
            Certificate::TYPE_YOGA_ALLIANCE,
        ],
    ],
    'templates' => [
        Certificate::TYPE_BIKRAM => [
            'template_file' => '1.jpg',
            'template_source_url' => 'https://online-v2.26and2yoga.com/wp-content/uploads/2025/12/1.jpg',
            'file_name_suffix' => 'bikram-yoga-certificate',
            'placement' => [
                'x' => 845,
                'y' => 805,
                'font_size' => 42,
                'font_color' => '#6F513D',
                'alignment' => 'center',
                'font_family' => 'dejavu_sans',
            ],
        ],
        Certificate::TYPE_YOGA_ALLIANCE => [
            'template_file' => '2.jpg',
            'template_source_url' => 'https://online-v2.26and2yoga.com/wp-content/uploads/2025/12/2.jpg',
            'file_name_suffix' => 'yoga-alliance-certificate',
            'placement' => [
                'x' => 845,
                'y' => 805,
                'font_size' => 42,
                'font_color' => '#6F513D',
                'alignment' => 'center',
                'font_family' => 'dejavu_sans',
            ],
        ],
    ],
];
