<?php

namespace Database\Factories;

use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailLog>
 */
class EmailLogFactory extends Factory
{
    protected $model = EmailLog::class;

    public function definition(): array
    {
        $type = EmailNotificationTypeRegistry::types()[0]['value'];

        return [
            'email_template_id' => EmailTemplate::factory(),
            'notification_type' => $type,
            'reference_type' => 'test',
            'reference_id' => null,
            'recipient_type' => 'user',
            'recipient_email' => fake()->safeEmail(),
            'subject' => 'Test subject',
            'body_snapshot' => 'Test body',
            'status' => 'sent',
            'error_message' => null,
            'sent_at' => now(),
        ];
    }
}
