<?php

namespace Database\Factories;

use App\Models\EmailTemplate;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailTemplate>
 */
class EmailTemplateFactory extends Factory
{
    protected $model = EmailTemplate::class;

    public function definition(): array
    {
        $type = EmailNotificationTypeRegistry::types()[0];

        return [
            'notification_type' => $type['value'],
            'notification_name' => $type['label'],
            'is_enabled' => true,
            'admin_recipients' => 'admin@yogafx.test',
            'subject_user' => 'Hello {{ user_name }}',
            'body_user' => 'This is a test for {{ notification_type }}.',
            'subject_admin' => 'Admin notice {{ notification_type }}',
            'body_admin' => 'Admin body for {{ notification_type }}.',
        ];
    }
}
