<?php

use App\Services\EmailNotificationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('email-notifications:send-reminders', function (EmailNotificationService $emailNotificationService) {
    $sentCount = $emailNotificationService->sendInactivityReminders();

    $this->info("Reminder notifications sent: {$sentCount}");
})->purpose('Send reminder email notifications to inactive students');

Schedule::command('email-notifications:send-reminders')->daily();
