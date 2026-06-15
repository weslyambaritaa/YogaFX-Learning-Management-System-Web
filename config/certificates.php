<?php

use App\Models\AccessTier;
use App\Models\Certificate;

return [
    'storage_disk' => 'local',
    'template_directory' => 'certificate-templates',
    'output_directory' => 'certificates',
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
            'file_name_suffix' => 'bikram-yoga-certificate',
            'placement' => [
                'x' => 800,
                'y' => 770,
                'font_size' => 42,
                'font_color' => '#6F513D',
                'alignment' => 'center',
                'font_family' => 'dejavu_sans',
            ],
        ],
        Certificate::TYPE_YOGA_ALLIANCE => [
            'template_file' => '2.jpg',
            'file_name_suffix' => 'yoga-alliance-certificate',
            'placement' => [
                'x' => 800,
                'y' => 770,
                'font_size' => 42,
                'font_color' => '#6F513D',
                'alignment' => 'center',
                'font_family' => 'dejavu_sans',
            ],
        ],
    ],
];
