<?php

return [
    'currency_to_usd_rates' => [
        'USD' => (float) env('ADMIN_DASHBOARD_RATE_USD', 1),
        'GBP' => (float) env('ADMIN_DASHBOARD_RATE_GBP', 1.35),
        'EUR' => (float) env('ADMIN_DASHBOARD_RATE_EUR', 1.08),
        'IDR' => (float) env('ADMIN_DASHBOARD_RATE_IDR', 0.000061),
    ],
];
