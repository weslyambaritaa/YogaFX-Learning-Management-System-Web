<?php

namespace App\Services;

use App\Events\EmailNotifications\ReminderTriggered;
use App\Events\EmailNotifications\ResetPasswordRequested;
use App\Mail\TemplatedNotificationMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\User;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Facades\Mail;
use Throwable;
use RuntimeException;

class EmailNotificationService
{
    public function findOrCreateTemplate(string $notificationType): EmailTemplate
    {
        return EmailTemplate::query()->firstOrCreate(
            ['notification_type' => $notificationType],
            [
                'notification_name' => EmailNotificationTypeRegistry::labelFor($notificationType),
                'is_enabled' => false,
            ],
        );
    }

    public function findTemplate(string $notificationType): ?EmailTemplate
    {
        return EmailTemplate::query()
            ->where('notification_type', $notificationType)
            ->first();
    }

    /**
     * @return array{status: string, tone: string, message: string}
     */
    public function sendTest(string $notificationType, string $sendTo): array
    {
        $template = $this->findOrCreateTemplate($notificationType);
        $payload = $this->samplePayloadFor($notificationType, $sendTo);
        $delivery = [
            'recipient_type' => 'test',
            'recipient_email' => $sendTo,
            'subject' => '',
            'body' => '',
            'variant_label' => 'Test Email',
        ];

        try {
            $delivery = $this->buildTestDelivery($template, $payload, $sendTo);
            $mailer = $this->activeSendTestMailer();

            if ($mailer['transport'] !== 'smtp') {
                $this->storeLog(
                    template: $template,
                    notificationType: $notificationType,
                    subject: $delivery['subject'],
                    body: $delivery['body'],
                    recipientEmail: $sendTo,
                    recipientType: $delivery['recipient_type'],
                    status: 'not_sent',
                    referenceType: 'test',
                    referenceId: null,
                    errorMessage: $mailer['message'],
                );

                return [
                    'status' => 'email-template-test-not-sent',
                    'tone' => 'warning',
                    'message' => $mailer['message'],
                ];
            }

            Mail::mailer($mailer['name'])
                ->to($sendTo)
                ->send(new TemplatedNotificationMail(
                    $delivery['subject'],
                    $delivery['body'],
                    $delivery['variant_label'],
                ));

            $this->storeLog(
                template: $template,
                notificationType: $notificationType,
                subject: $delivery['subject'],
                body: $delivery['body'],
                recipientEmail: $sendTo,
                recipientType: $delivery['recipient_type'],
                status: 'sent',
                referenceType: 'test',
                referenceId: null,
            );

            return [
                'status' => 'email-template-test-sent',
                'tone' => 'success',
                'message' => sprintf(
                    'Test email sent successfully to %s using the active SMTP mailer.',
                    $sendTo,
                ),
            ];
        } catch (Throwable $throwable) {
            $this->storeLog(
                template: $template,
                notificationType: $notificationType,
                subject: $delivery['subject'],
                body: $delivery['body'],
                recipientEmail: $sendTo,
                recipientType: $delivery['recipient_type'],
                status: 'failed',
                referenceType: 'test',
                referenceId: null,
                errorMessage: $throwable->getMessage(),
            );

            return [
                'status' => 'email-template-test-failed',
                'tone' => 'error',
                'message' => $throwable instanceof RuntimeException
                    ? $throwable->getMessage()
                    : 'SMTP delivery failed: '.$throwable->getMessage(),
            ];
        }
    }

    public function sendAutomated(
        string $notificationType,
        array $payload,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $template = EmailTemplate::query()
            ->where('notification_type', $notificationType)
            ->first();

        if (! $template || ! $template->is_enabled) {
            return;
        }

        foreach ($this->buildDeliveries($template, $payload) as $delivery) {
            $this->deliver(
                template: $template,
                notificationType: $notificationType,
                subject: $delivery['subject'],
                body: $delivery['body'],
                recipientEmail: $delivery['recipient_email'],
                recipientType: $delivery['recipient_type'],
                referenceType: $referenceType,
                referenceId: $referenceId,
                variantLabel: $delivery['variant_label'],
            );
        }
    }

    public function shouldHandlePasswordResetTemplate(): bool
    {
        $template = $this->findTemplate(EmailNotificationTypeRegistry::RESET_PASSWORD);

        return $template instanceof EmailTemplate
            && $template->is_enabled
            && filled($template->subject_user)
            && filled($template->body_user);
    }

    public function sendPasswordResetRequested(User $user, string $token): void
    {
        event(new ResetPasswordRequested([
            'user_name' => $user->name,
            'user_email' => $user->email,
            'reset_url' => route('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]),
            'reset_expiry_minutes' => (string) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60,
            ),
            'login_url' => route('login'),
        ], 'user', $user->id));
    }

    public function sendInactivityReminders(): int
    {
        $template = $this->findTemplate(EmailNotificationTypeRegistry::REMINDER);

        if (! $template || ! $template->is_enabled) {
            return 0;
        }

        $threshold = now()->subDays(7);
        $sentCount = 0;

        User::query()
            ->where('role', User::ROLE_STUDENT)
            ->whereNotNull('access_tier_id')
            ->with(['lessonProgresses' => fn ($query) => $query
                ->select('id', 'user_id', 'updated_at')
                ->orderByDesc('updated_at')])
            ->whereHas('lessonProgresses')
            ->get()
            ->each(function (User $user) use ($threshold, &$sentCount): void {
                $lastProgressUpdate = optional(
                    $user->lessonProgresses->sortByDesc('updated_at')->first(),
                )->updated_at;

                if (! $lastProgressUpdate || $lastProgressUpdate->gt($threshold)) {
                    return;
                }

                $alreadyRemindedRecently = EmailLog::query()
                    ->where('notification_type', EmailNotificationTypeRegistry::REMINDER)
                    ->where('reference_type', 'user')
                    ->where('reference_id', $user->id)
                    ->where('status', 'sent')
                    ->where('sent_at', '>=', $threshold)
                    ->exists();

                if ($alreadyRemindedRecently) {
                    return;
                }

                $inactiveDays = (string) $lastProgressUpdate->diffInDays(now());

                event(new ReminderTriggered([
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'last_activity_date' => $lastProgressUpdate->toDateString(),
                    'inactive_days' => $inactiveDays,
                    'dashboard_url' => route('dashboard'),
                    'login_url' => route('login'),
                ], 'user', $user->id));

                $sentCount++;
            });

        return $sentCount;
    }

    /**
     * @return array<int, string>
     */
    public function parseRecipients(?string $recipients): array
    {
        if (! is_string($recipients) || trim($recipients) === '') {
            return [];
        }

        return collect(preg_split('/[\s,;]+/', $recipients) ?: [])
            ->filter(fn (?string $email) => filled($email))
            ->unique()
            ->values()
            ->all();
    }

    public function render(string $content, array $payload): string
    {
        return preg_replace_callback('/{{\s*([\w_]+)\s*}}/', function (array $matches) use ($payload) {
            $key = $matches[1] ?? '';

            return (string) ($payload[$key] ?? '');
        }, $content) ?? $content;
    }

    /**
     * @return array<int, array{recipient_type: string, recipient_email: string, subject: string, body: string, variant_label: string}>
     */
    private function buildDeliveries(
        EmailTemplate $template,
        array $payload,
        ?string $testRecipient = null,
        bool $isTest = false,
    ): array {
        $deliveries = [];

        if (filled($template->subject_user) && filled($template->body_user) && filled($payload['user_email'] ?? $testRecipient)) {
            $deliveries[] = [
                'recipient_type' => $isTest ? 'test_user' : 'user',
                'recipient_email' => $testRecipient ?: (string) $payload['user_email'],
                'subject' => $this->render((string) $template->subject_user, $payload),
                'body' => $this->render((string) $template->body_user, $payload),
                'variant_label' => 'User Email',
            ];
        }

        if (filled($template->subject_admin) && filled($template->body_admin)) {
            foreach ($this->parseRecipients($template->admin_recipients) as $recipient) {
                $deliveries[] = [
                    'recipient_type' => $isTest ? 'test_admin' : 'admin',
                    'recipient_email' => $testRecipient ?: $recipient,
                    'subject' => $this->render((string) $template->subject_admin, $payload),
                    'body' => $this->render((string) $template->body_admin, $payload),
                    'variant_label' => 'Admin Email',
                ];

                if ($isTest) {
                    break;
                }
            }
        }

        return collect($deliveries)
            ->unique(fn (array $delivery) => implode('|', [
                $delivery['recipient_type'],
                $delivery['recipient_email'],
                $delivery['subject'],
                $delivery['body'],
            ]))
            ->values()
            ->all();
    }

    private function deliver(
        EmailTemplate $template,
        string $notificationType,
        string $subject,
        string $body,
        string $recipientEmail,
        string $recipientType,
        ?string $referenceType,
        ?int $referenceId,
        string $variantLabel,
    ): void {
        try {
            Mail::to($recipientEmail)->send(
                new TemplatedNotificationMail($subject, $body, $variantLabel),
            );

            $this->storeLog(
                template: $template,
                notificationType: $notificationType,
                subject: $subject,
                body: $body,
                recipientEmail: $recipientEmail,
                recipientType: $recipientType,
                status: 'sent',
                referenceType: $referenceType,
                referenceId: $referenceId,
            );
        } catch (Throwable $throwable) {
            $this->storeLog(
                template: $template,
                notificationType: $notificationType,
                subject: $subject,
                body: $body,
                recipientEmail: $recipientEmail,
                recipientType: $recipientType,
                status: 'failed',
                referenceType: $referenceType,
                referenceId: $referenceId,
                errorMessage: $throwable->getMessage(),
            );

            throw $throwable;
        }
    }

    /**
     * @return array{recipient_type: string, recipient_email: string, subject: string, body: string, variant_label: string}
     */
    private function buildTestDelivery(
        EmailTemplate $template,
        array $payload,
        string $sendTo,
    ): array {
        if (filled($template->subject_user) && filled($template->body_user)) {
            return [
                'recipient_type' => 'test_user',
                'recipient_email' => $sendTo,
                'subject' => $this->renderStrict((string) $template->subject_user, $payload, 'user subject'),
                'body' => $this->renderStrict((string) $template->body_user, $payload, 'user body'),
                'variant_label' => 'User Email',
            ];
        }

        if (filled($template->subject_admin) && filled($template->body_admin)) {
            return [
                'recipient_type' => 'test_admin',
                'recipient_email' => $sendTo,
                'subject' => $this->renderStrict((string) $template->subject_admin, $payload, 'admin subject'),
                'body' => $this->renderStrict((string) $template->body_admin, $payload, 'admin body'),
                'variant_label' => 'Admin Email',
            ];
        }

        throw new RuntimeException(
            'The active template is incomplete. Fill at least one full email variant before sending a test.',
        );
    }

    private function renderStrict(string $content, array $payload, string $context): string
    {
        $missingKeys = $this->missingMergeTags($content, $payload);

        if ($missingKeys !== []) {
            throw new RuntimeException(sprintf(
                'The %s could not be rendered because test data is missing for: %s.',
                $context,
                implode(', ', $missingKeys),
            ));
        }

        return $this->render($content, $payload);
    }

    /**
     * @return array<int, string>
     */
    private function missingMergeTags(string $content, array $payload): array
    {
        preg_match_all('/{{\s*([\w_]+)\s*}}/', $content, $matches);

        return collect($matches[1] ?? [])
            ->filter(fn (mixed $key) => is_string($key) && $key !== '')
            ->unique()
            ->filter(function (string $key) use ($payload): bool {
                if (! array_key_exists($key, $payload)) {
                    return true;
                }

                $value = $payload[$key];

                return $value === null || $value === '';
            })
            ->values()
            ->all();
    }

    /**
     * @return array{name: string, transport: string, message: string}
     */
    private function activeSendTestMailer(): array
    {
        $mailerName = (string) config('mail.default', '');
        $transport = (string) config("mail.mailers.{$mailerName}.transport", '');

        if ($mailerName === '' || $transport === '') {
            return [
                'name' => $mailerName,
                'transport' => $transport,
                'message' => 'The active mailer is not configured correctly. Please check your mail configuration.',
            ];
        }

        if ($transport !== 'smtp') {
            return [
                'name' => $mailerName,
                'transport' => $transport,
                'message' => sprintf(
                    'The active mailer is set to "%s" (%s), so no real SMTP test email was sent. Set MAIL_MAILER=smtp to send a real test email.',
                    $mailerName,
                    $transport,
                ),
            ];
        }

        $missingConfig = collect([
            'MAIL_HOST' => config("mail.mailers.{$mailerName}.host"),
            'MAIL_PORT' => config("mail.mailers.{$mailerName}.port"),
            'MAIL_FROM_ADDRESS' => config('mail.from.address'),
        ])->filter(fn (mixed $value) => ! filled($value))
            ->keys()
            ->values()
            ->all();

        if ($missingConfig !== []) {
            return [
                'name' => $mailerName,
                'transport' => $transport,
                'message' => 'SMTP configuration is incomplete. Missing: '.implode(', ', $missingConfig).'.',
            ];
        }

        return [
            'name' => $mailerName,
            'transport' => $transport,
            'message' => '',
        ];
    }

    private function storeLog(
        EmailTemplate $template,
        string $notificationType,
        string $subject,
        string $body,
        string $recipientEmail,
        string $recipientType,
        string $status,
        ?string $referenceType,
        ?int $referenceId,
        ?string $errorMessage = null,
    ): void {
        EmailLog::query()->create([
            'email_template_id' => $template->id,
            'notification_type' => $notificationType,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'recipient_type' => $recipientType,
            'recipient_email' => $recipientEmail,
            'subject' => $subject,
            'body_snapshot' => $body,
            'status' => $status,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ]);
    }

    private function samplePayloadFor(string $notificationType, string $sendTo): array
    {
        $base = [
            'notification_type' => $notificationType,
            'app_name' => config('app.name', 'YogaFX LMS'),
            'user_name' => 'Test Student',
            'user_email' => $sendTo,
            'admin_email' => config('mail.from.address'),
            'assignment_type' => 'Standing & Floor',
            'feedback' => 'Please improve lighting and camera angle on the re-upload.',
            'lesson_title' => 'Sample Lesson',
            'module_title' => 'Sample Module',
            'completion_date' => now()->format('Y-m-d H:i'),
            'module_progress' => '100%',
            'course_progress' => '67%',
            'study_time' => '3 hours 25 minutes',
            'certificate_type' => 'YogaFX Certificate',
            'certificate_file_name' => 'sample-certificate.pdf',
            'access_tier' => 'master_class',
            'access_tier_label' => 'Masterclass',
            'registration_date' => now()->toDateString(),
            'reset_url' => route('password.reset', [
                'token' => 'sample-reset-token',
                'email' => $sendTo,
            ]),
            'reset_expiry_minutes' => (string) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60,
            ),
            'assessment_title' => 'Sample Assessment',
            'assessment_score' => '85',
            'completed_at' => now()->format('Y-m-d H:i'),
            'result_url' => route('dashboard'),
            'course_title' => 'YogaFX Core Journey',
            'completion_date' => now()->toDateString(),
            'last_activity_date' => now()->subDays(8)->toDateString(),
            'inactive_days' => '8',
            'dashboard_url' => route('dashboard'),
            'login_url' => route('login'),
        ];

        $base['notification_type'] = $notificationType;

        return $base;
    }
}
