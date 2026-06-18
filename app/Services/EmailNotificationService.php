<?php

namespace App\Services;

use App\Events\EmailNotifications\ReminderTriggered;
use App\Events\EmailNotifications\ResetPasswordRequested;
use App\Mail\TemplatedNotificationMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\User;
use App\Models\UserSession;
use App\Support\EmailNotificationTemplateDefaults;
use App\Support\EmailNotificationTypeRegistry;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Throwable;
use RuntimeException;

class EmailNotificationService
{
    private const EMAIL_PLACEHOLDER_PATTERN = '/{{\s*([\w_]+)\s*}}|(?<!{){\s*([\w_]+)\s*}(?!})/';
    private const REMINDER_INACTIVITY_THRESHOLD_MINUTES = 10;

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
    public function sendTest(string $notificationType, string $sendTo, ?int $moduleId = null): array
    {
        $template = $this->preparedTemplate($notificationType);
        $payload = $this->samplePayloadFor($notificationType, $sendTo, $moduleId);
        $deliveries = [];

        try {
            $deliveries = $this->buildTestDeliveries($template, $payload, $sendTo);
            $mailer = $this->activeSendTestMailer();

            if ($mailer['transport'] !== 'smtp') {
                foreach ($deliveries as $delivery) {
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
                }

                return [
                    'status' => 'email-template-test-not-sent',
                    'tone' => 'warning',
                    'message' => $mailer['message'],
                ];
            }

            foreach ($deliveries as $delivery) {
                $mailable = new TemplatedNotificationMail(
                    $delivery['subject'],
                    $delivery['body'],
                    $delivery['variant_label'],
                );

                if (app()->environment('testing')) {
                    Mail::to($sendTo)->send($mailable);
                } else {
                    Mail::mailer($mailer['name'])
                        ->to($sendTo)
                        ->send($mailable);
                }

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
            }

            return [
                'status' => 'email-template-test-sent',
                'tone' => 'success',
                'message' => sprintf(
                    'Test email sent successfully to %s using the active SMTP mailer.',
                    $sendTo,
                ),
            ];
        } catch (Throwable $throwable) {
            foreach ($deliveries === [] ? [[
                'recipient_type' => 'test',
                'recipient_email' => $sendTo,
                'subject' => '',
                'body' => '',
                'variant_label' => 'Test Email',
            ]] : $deliveries as $delivery) {
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
            }

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
        $template = $this->preparedTemplate($notificationType);

        if (! $template->is_enabled) {
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

    public function sendAssignmentApprovedNotification(
        array $payload,
        ?string $referenceType = null,
        ?int $referenceId = null,
    ): void {
        $template = $this->preparedTemplate(EmailNotificationTypeRegistry::ASSIGNMENT_APPROVED);

        if (! $template->is_enabled) {
            return;
        }

        foreach ($this->buildDeliveries($template, $payload) as $delivery) {
            $this->deliver(
                template: $template,
                notificationType: EmailNotificationTypeRegistry::ASSIGNMENT_APPROVED,
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
        $template = $this->preparedTemplate(EmailNotificationTypeRegistry::RESET_PASSWORD);

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

    public function sendStudentPasswordChangeRequested(
        User $user,
        string $changePasswordUrl,
        string $otpCode,
        int $expiresInMinutes,
    ): void {
        $template = $this->preparedTemplate(EmailNotificationTypeRegistry::RESET_PASSWORD);
        $payload = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'reset_url' => $changePasswordUrl,
            'password_change_url' => $changePasswordUrl,
            'otp_code' => $otpCode,
            'reset_expiry_minutes' => (string) $expiresInMinutes,
            'login_url' => route('login'),
        ];

        $deliveries = $this->buildDeliveries($template, $payload);

        foreach ($deliveries as $delivery) {
            $body = $delivery['body'];

            if ($delivery['recipient_type'] === 'user') {
                $body = $this->ensurePasswordChangeVerificationBlock(
                    $body,
                    (string) $template->body_user,
                    $payload,
                );
            }

            $this->deliver(
                template: $template,
                notificationType: EmailNotificationTypeRegistry::RESET_PASSWORD,
                subject: $delivery['subject'],
                body: $body,
                recipientEmail: $delivery['recipient_email'],
                recipientType: $delivery['recipient_type'],
                referenceType: 'student_password_change',
                referenceId: $user->id,
                variantLabel: $delivery['variant_label'],
            );
        }
    }

    public function sendInactivityReminders(): int
    {
        $template = $this->preparedTemplate(EmailNotificationTypeRegistry::REMINDER);

        if (! $template->is_enabled) {
            return 0;
        }

        $threshold = now()->subMinutes(self::REMINDER_INACTIVITY_THRESHOLD_MINUTES);
        $sentCount = 0;

        User::query()
            ->where('role', User::ROLE_STUDENT)
            ->whereNotNull('access_tier_id')
            ->get()
            ->each(function (User $user) use ($threshold, &$sentCount): void {
                if ($this->studentHasCompletedAccessibleCourse($user)) {
                    return;
                }

                if ($this->studentHasActiveSession($user)) {
                    return;
                }

                $lastLoginAt = $this->latestStudentLoginAt($user);

                if (! $lastLoginAt || $lastLoginAt->gt($threshold)) {
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

                $inactiveMinutes = max($lastLoginAt->diffInMinutes(now()), self::REMINDER_INACTIVITY_THRESHOLD_MINUTES);

                event(new ReminderTriggered([
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'last_activity_date' => $lastLoginAt->toDateTimeString(),
                    'inactive_days' => (string) $inactiveMinutes,
                    'dashboard_url' => route('student.dashboard'),
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
        return preg_replace_callback(self::EMAIL_PLACEHOLDER_PATTERN, function (array $matches) use ($payload) {
            $key = $matches[1] !== '' ? $matches[1] : ($matches[2] ?? '');

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

    private function preparedTemplate(string $notificationType): EmailTemplate
    {
        $template = $this->findOrCreateTemplate($notificationType);
        $defaults = EmailNotificationTemplateDefaults::for($notificationType);
        $hasChanges = false;

        foreach (['subject_user', 'body_user', 'subject_admin', 'body_admin'] as $field) {
            if (! filled($template->{$field}) && filled($defaults[$field] ?? null)) {
                $template->{$field} = $defaults[$field];
                $hasChanges = true;
            }
        }

        if (($defaults['auto_enable'] ?? false) && ! $template->is_enabled) {
            $template->is_enabled = true;
            $hasChanges = true;
        }

        if ($hasChanges) {
            $template->save();
        }

        return $template;
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
            report($throwable);
        }
    }

    /**
     * @return array<int, array{recipient_type: string, recipient_email: string, subject: string, body: string, variant_label: string}>
     */
    private function buildTestDeliveries(
        EmailTemplate $template,
        array $payload,
        string $sendTo,
    ): array {
        $deliveries = [];

        if (filled($template->subject_user) && filled($template->body_user)) {
            $deliveries[] = [
                'recipient_type' => 'test_user',
                'recipient_email' => $sendTo,
                'subject' => $this->renderStrict((string) $template->subject_user, $payload, 'user subject'),
                'body' => $this->renderStrict((string) $template->body_user, $payload, 'user body'),
                'variant_label' => 'User Email',
            ];
        }

        if (filled($template->subject_admin) && filled($template->body_admin)) {
            $deliveries[] = [
                'recipient_type' => 'test_admin',
                'recipient_email' => $sendTo,
                'subject' => $this->renderStrict((string) $template->subject_admin, $payload, 'admin subject'),
                'body' => $this->renderStrict((string) $template->body_admin, $payload, 'admin body'),
                'variant_label' => 'Admin Email',
            ];
        }

        if ($deliveries !== []) {
            return $deliveries;
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
        return collect($this->extractPlaceholderKeys($content))
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
     * @return array<int, string>
     */
    private function extractPlaceholderKeys(string $content): array
    {
        preg_match_all(self::EMAIL_PLACEHOLDER_PATTERN, $content, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $match): ?string {
                $key = $match[1] !== '' ? $match[1] : ($match[2] ?? '');

                return is_string($key) && $key !== '' ? $key : null;
            })
            ->filter()
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

        if (app()->environment('testing')) {
            return [
                'name' => $mailerName !== '' ? $mailerName : 'array',
                'transport' => 'smtp',
                'message' => '',
            ];
        }

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

    private function ensurePasswordChangeVerificationBlock(
        string $renderedBody,
        string $templateBody,
        array $payload,
    ): string {
        $sections = [];

        if (
            ! str_contains($templateBody, 'otp_code')
            && filled($payload['otp_code'] ?? null)
        ) {
            $sections[] = '<p>Your one-time password code: <strong>'.e((string) $payload['otp_code']).'</strong></p>';
        }

        if (
            ! str_contains($templateBody, 'password_change_url')
            && ! str_contains($templateBody, 'reset_url')
            && filled($payload['password_change_url'] ?? null)
        ) {
            $url = (string) $payload['password_change_url'];
            $escapedUrl = e($url);
            $sections[] = '<p>Continue here to change your password: <a href="'.$escapedUrl.'">'.$escapedUrl.'</a></p>';
        }

        if ($sections === []) {
            return $renderedBody;
        }

        return $renderedBody.implode('', $sections);
    }

    private function samplePayloadFor(string $notificationType, string $sendTo, ?int $moduleId = null): array
    {
        $selectedModule = $moduleId !== null
            ? Module::query()->find($moduleId, ['id', 'title'])
            : null;

        $base = [
            'notification_type' => $notificationType,
            'app_name' => config('app.name', 'YogaFX LMS'),
            'user_name' => 'Test Student',
            'user_email' => $sendTo,
            'admin_email' => config('mail.from.address'),
            'assignment_type' => 'Standing & Floor',
            'feedback' => 'Please improve lighting and camera angle on the re-upload.',
            'lesson_title' => 'Sample Lesson',
            'module_title' => $selectedModule?->title ?? 'Sample Module',
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
            'result_url' => route('student.dashboard'),
            'course_title' => 'YogaFX Core Journey',
            'completion_date' => now()->toDateString(),
            'last_activity_date' => now()->subDays(8)->toDateString(),
            'inactive_days' => '8',
            'dashboard_url' => route('student.dashboard'),
            'login_url' => route('login'),
        ];

        $base['notification_type'] = $notificationType;

        return $base;
    }

    private function latestStudentLoginAt(User $user)
    {
        if ($this->studentSessionSchemaReady()) {
            $latestSession = UserSession::query()
                ->where('user_id', $user->id)
                ->orderByDesc('login_at')
                ->first(['login_at']);

            if ($latestSession?->login_at) {
                return $latestSession->login_at;
            }
        }

        return $user->created_at;
    }

    private function studentHasActiveSession(User $user): bool
    {
        if (! $this->studentSessionSchemaReady()) {
            return false;
        }

        return UserSession::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->exists();
    }

    private function studentHasCompletedAccessibleCourse(User $user): bool
    {
        if ($user->access_tier_id === null) {
            return false;
        }

        $accessibleModules = Module::query()
            ->whereHas('accessTiers', fn ($query) => $query->where('access_tiers.id', $user->access_tier_id))
            ->with([
                'lessons' => fn ($query) => $query
                    ->whereHas('accessTiers', fn ($lessonQuery) => $lessonQuery->where('access_tiers.id', $user->access_tier_id))
                    ->orderBy('sort_order')
                    ->orderBy('title'),
            ])
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get(['id']);

        $modulesWithLessons = $accessibleModules
            ->filter(fn (Module $module) => $module->lessons->isNotEmpty())
            ->values();

        if ($modulesWithLessons->isEmpty()) {
            return false;
        }

        $completedLessonIds = LessonProgress::query()
            ->where('user_id', $user->id)
            ->where('is_done', true)
            ->whereIn('lesson_id', $modulesWithLessons->flatMap(fn (Module $module) => $module->lessons->pluck('id')))
            ->pluck('lesson_id')
            ->map(fn ($lessonId) => (int) $lessonId)
            ->unique()
            ->flip();

        return $modulesWithLessons->every(
            fn (Module $module) => $module->lessons->every(
                fn ($lesson) => $completedLessonIds->has((int) $lesson->id),
            ),
        );
    }

    private function studentSessionSchemaReady(): bool
    {
        return Schema::hasTable('user_sessions');
    }
}
