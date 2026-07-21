<?php

namespace App\Services;

use App\Events\EmailNotifications\ReminderTriggered;
use App\Events\EmailNotifications\ResetPasswordRequested;
use App\Mail\TemplatedNotificationMail;
use App\Models\EmailLog;
use App\Models\EmailTemplate;
use App\Models\Invoice;
use App\Models\LessonProgress;
use App\Models\Module;
use App\Models\OnboardingState;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserSession;
use App\Models\Lesson;
use App\Support\EmailNotificationTemplateDefaults;
use App\Support\EmailNotificationTypeRegistry;
use App\Support\PublicUrl;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Throwable;
use RuntimeException;

class EmailNotificationService
{
    private const EMAIL_PLACEHOLDER_PATTERN = '/{{\s*([\w_]+)\s*}}|(?<!{){\s*([\w_]+)\s*}(?!})/';
    private const REMINDER_INACTIVITY_THRESHOLD_MINUTES = 10;

    public function __construct(
        private readonly EmailBrandingService $emailBrandingService,
    ) {}

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

    public function findOrPrepareTemplate(string $notificationType): EmailTemplate
    {
        return $this->preparedTemplate($notificationType);
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
                    $snapshotBody = $this->brandedBodySnapshot(
                        $delivery['subject'],
                        $delivery['body'],
                        $delivery['variant_label'],
                    );

                    $this->storeLog(
                        template: $template,
                        notificationType: $notificationType,
                        subject: $delivery['subject'],
                        body: $snapshotBody,
                        recipientEmail: $delivery['recipient_email'],
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
                    [],
                    $this->emailBrandingService->currentBrandingPayload(),
                );

                if (app()->environment('testing')) {
                    Mail::to($delivery['recipient_email'])->send($mailable);
                } else {
                    Mail::mailer($mailer['name'])
                        ->to($delivery['recipient_email'])
                        ->send($mailable);
                }

                $this->storeLog(
                    template: $template,
                    notificationType: $notificationType,
                    subject: $delivery['subject'],
                    body: $mailable->previewHtml(),
                    recipientEmail: $delivery['recipient_email'],
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
                $snapshotBody = $this->brandedBodySnapshot(
                    $delivery['subject'],
                    $delivery['body'],
                    $delivery['variant_label'],
                );

                $this->storeLog(
                    template: $template,
                    notificationType: $notificationType,
                    subject: $delivery['subject'],
                    body: $snapshotBody,
                    recipientEmail: $delivery['recipient_email'],
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
        array $attachments = [],
    ): void {
        $template = $this->preparedTemplate($notificationType);

        if (! $template->is_enabled) {
            return;
        }

        $deliveries = $this->buildDeliveries($template, $payload);

        if ($notificationType === EmailNotificationTypeRegistry::RESET_PASSWORD) {
            $deliveries = $this->excludeUserEmailFromAdminDeliveries($deliveries, $payload);
        }

        foreach ($deliveries as $delivery) {
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
                attachments: $attachments,
            );
        }
    }

    /**
     * @param  array{name: string, mime: string, data: string}  $attachment
     */
    public function sendWorkbookSentNotification(
        User $user,
        Lesson $lesson,
        array $attachment,
    ): void {
        $template = $this->preparedTemplate(EmailNotificationTypeRegistry::WORKBOOK_SENT);

        if (! $template->is_enabled) {
            return;
        }

        $payload = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'lesson_title' => $lesson->title,
            'module_title' => $lesson->module?->title ?? '',
            'workbook_file_name' => $attachment['name'],
            'dashboard_url' => route('student.dashboard'),
        ];

        foreach ($this->buildDeliveries($template, $payload) as $delivery) {
            $this->deliver(
                template: $template,
                notificationType: EmailNotificationTypeRegistry::WORKBOOK_SENT,
                subject: $delivery['subject'],
                body: $delivery['body'],
                recipientEmail: $delivery['recipient_email'],
                recipientType: $delivery['recipient_type'],
                referenceType: 'lesson_workbook',
                referenceId: $lesson->id,
                variantLabel: $delivery['variant_label'],
                attachments: $delivery['recipient_type'] === 'user' ? [$attachment] : [],
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

    public function sendSignupNotification(User $user, ?OnboardingState $onboardingState = null): void
    {
        $referenceType = 'user';
        $referenceId = $user->id;

        if ($this->signupNotificationAlreadySent($user, $onboardingState)) {
            return;
        }

        $this->sendAutomated(
            EmailNotificationTypeRegistry::SIGNUP,
            [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'admin_email' => config('mail.from.address'),
                'access_tier' => $user->accessTier?->slug,
                'access_tier_label' => $user->accessTier?->name,
                'registration_date' => optional(
                    $onboardingState?->signup_completed_at
                    ?? $onboardingState?->pendingRegistration?->completed_at
                    ?? $user->created_at,
                )->toDateString() ?? now()->toDateString(),
                'dashboard_url' => route('student.dashboard'),
                'login_url' => $this->studentLoginUrl(),
            ],
            $referenceType,
            $referenceId,
        );
    }

    public function sendPaymentSuccessNotification(
        Invoice $invoice,
        Payment $paymentActivity,
        ?OnboardingState $onboardingState = null,
        array $attachments = [],
    ): void {
        if ($this->notificationAlreadySent(
            EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            'payment_activity',
            $paymentActivity->id,
        )) {
            return;
        }

        $invoice->loadMissing('user.accessTier', 'pendingRegistration.accessTier', 'accessTier', 'package');
        $onboardingState?->loadMissing('user.accessTier', 'pendingRegistration.accessTier');
        $paymentActivity->loadMissing('invoice.accessTier');

        $user = $invoice->user;
        $pendingRegistration = $invoice->pendingRegistration;
        $accessTier = $user?->accessTier
            ?? $pendingRegistration?->accessTier
            ?? $onboardingState?->pendingRegistration?->accessTier
            ?? $invoice->accessTier;
        $userName = $user?->name ?: $pendingRegistration?->fullName() ?: 'Student';
        $userEmail = $user?->email ?: ($pendingRegistration?->email ?: '');
        $packagePaymentType = $invoice->package_payment_type
            ?? $invoice->package?->normalizedPaymentType()
            ?? Package::PAYMENT_TYPE_PAID;

        [$paymentSuccessSubject, $paymentSuccessMessage, $paymentSuccessAdminMessage] = match ($packagePaymentType) {
            Package::PAYMENT_TYPE_FREE => [
                'Registration ready',
                'Congratulations. Your free registration for <strong>'.$accessTier?->name.'</strong> is ready.',
                'A new YogaFX free registration is ready for onboarding.',
            ],
            Package::PAYMENT_TYPE_DONATION => [
                'Donation received',
                'Thank you for your donation. Your access for <strong>'.$accessTier?->name.'</strong> is ready.',
                'A new YogaFX donation payment has been completed successfully.',
            ],
            default => [
                'Payment successful',
                'Congratulations. Your payment for <strong>'.$accessTier?->name.'</strong> was successful.',
                'A new YogaFX onboarding payment has been completed successfully.',
            ],
        };

        $this->sendAutomated(
            EmailNotificationTypeRegistry::PAYMENT_SUCCESS,
            [
                'user_name' => $userName,
                'user_email' => $userEmail,
                'access_tier' => $accessTier?->slug,
                'access_tier_label' => $accessTier?->name,
                'package_title' => (string) ($invoice->package?->title ?? ''),
                'invoice_number' => (string) ($invoice->invoice_number ?? ''),
                'payment_reference' => (string) ($paymentActivity->payment_reference ?? ''),
                'amount' => number_format((float) $paymentActivity->amount_paid, 2, '.', ''),
                'currency_code' => (string) $paymentActivity->currency_code,
                'payment_success_subject' => $paymentSuccessSubject,
                'payment_success_message' => $paymentSuccessMessage,
                'payment_success_admin_message' => $paymentSuccessAdminMessage,
                'invoice_pdf_file_name' => isset($attachments[0]['name']) ? (string) $attachments[0]['name'] : '',
                'enrollment_url' => $this->paymentSuccessUrl($invoice, $onboardingState),
            ],
            'payment_activity',
            $paymentActivity->id,
            $attachments,
        );
    }

    public function sendEnrollmentSuccessNotification(OnboardingState $onboardingState): void
    {
        if ($this->notificationAlreadySent(
            EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            'onboarding_state',
            $onboardingState->id,
        )) {
            return;
        }

        $onboardingState->loadMissing('user.accessTier', 'pendingRegistration.accessTier');

        $user = $onboardingState->user;

        if (! $user instanceof User) {
            return;
        }

        $accessTier = $user->accessTier ?? $onboardingState->pendingRegistration?->accessTier;

        $this->sendAutomated(
            EmailNotificationTypeRegistry::ENROLLMENT_SUCCESS,
            [
                'user_name' => $user->name,
                'user_email' => $user->email,
                'access_tier' => $accessTier?->slug,
                'access_tier_label' => $accessTier?->name,
                'signup_url' => $this->signedOnboardingRoute('onboarding.signup.show', [
                    'onboardingState' => $onboardingState->id,
                ]),
            ],
            'onboarding_state',
            $onboardingState->id,
        );
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
            'reset_url' => $this->publicRoute('password.reset', [
                'token' => $token,
                'email' => $user->email,
            ]),
            'reset_expiry_minutes' => (string) config(
                'auth.passwords.'.config('auth.defaults.passwords').'.expire',
                60,
            ),
            'login_url' => $this->studentLoginUrl(),
        ], 'user', $user->id));
    }

    public function sendPasswordResetRequestedWithOtp(
        User $user,
        string $resetUrl,
        string $otpCode,
        int $expiresInMinutes,
    ): void {
        $this->sendPasswordResetForContext($user, $resetUrl, $otpCode, $expiresInMinutes, 'user');
    }

    public function sendStudentPasswordChangeRequested(
        User $user,
        string $changePasswordUrl,
        string $otpCode,
        int $expiresInMinutes,
    ): void {
        $this->sendPasswordResetForContext(
            user: $user,
            resetUrl: $changePasswordUrl,
            otpCode: $otpCode,
            expiresInMinutes: $expiresInMinutes,
            referenceType: 'student_password_change',
        );
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

                $lastAccessAt = $this->latestStudentAccessAt($user);

                if (! $lastAccessAt || $lastAccessAt->gt($threshold)) {
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

                $inactiveMinutes = max($lastAccessAt->diffInMinutes(now()), self::REMINDER_INACTIVITY_THRESHOLD_MINUTES);

                event(new ReminderTriggered([
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'last_activity_date' => $lastAccessAt->toDateTimeString(),
                    'inactive_days' => (string) $inactiveMinutes,
                    'dashboard_url' => route('student.dashboard'),
                    'login_url' => $this->studentLoginUrl(),
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
        $subjectUser = $this->normalizedTemplateContent((string) $template->subject_user, (string) $template->notification_type);
        $bodyUser = $this->normalizedTemplateContent((string) $template->body_user, (string) $template->notification_type);
        $subjectAdmin = $this->normalizedTemplateContent((string) $template->subject_admin, (string) $template->notification_type);
        $bodyAdmin = $this->normalizedTemplateContent((string) $template->body_admin, (string) $template->notification_type);

        if (filled($subjectUser) && filled($bodyUser) && filled($payload['user_email'] ?? $testRecipient)) {
            $deliveries[] = [
                'recipient_type' => $isTest ? 'test_user' : 'user',
                'recipient_email' => $testRecipient ?: (string) $payload['user_email'],
                'subject' => $this->render($subjectUser, $payload),
                'body' => $this->render($bodyUser, $payload),
                'variant_label' => 'User Email',
            ];
        }

        if (filled($subjectAdmin) && filled($bodyAdmin)) {
            foreach ($this->parseRecipients($template->admin_recipients) as $recipient) {
                $deliveries[] = [
                    'recipient_type' => $isTest ? 'test_admin' : 'admin',
                    'recipient_email' => $testRecipient ?: $recipient,
                    'subject' => $this->render($subjectAdmin, $payload),
                    'body' => $this->render($bodyAdmin, $payload),
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

        if ($template->wasRecentlyCreated) {
            foreach (['subject_user', 'body_user', 'subject_admin', 'body_admin'] as $field) {
                if (! filled($template->{$field}) && filled($defaults[$field] ?? null)) {
                    $template->{$field} = $defaults[$field];
                    $hasChanges = true;
                }
            }
        }

        if (($defaults['auto_enable'] ?? false) && $template->wasRecentlyCreated && ! $template->is_enabled) {
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
        array $attachments = [],
    ): void {
        try {
            Mail::to($recipientEmail)->send(
                new TemplatedNotificationMail(
                    $subject,
                    $body,
                    $variantLabel,
                    $attachments,
                    $this->emailBrandingService->currentBrandingPayload(),
                ),
            );

            $snapshotBody = $this->brandedBodySnapshot($subject, $body, $variantLabel, $attachments);

            $this->storeLog(
                template: $template,
                notificationType: $notificationType,
                subject: $subject,
                body: $snapshotBody,
                recipientEmail: $recipientEmail,
                recipientType: $recipientType,
                status: 'sent',
                referenceType: $referenceType,
                referenceId: $referenceId,
            );
        } catch (Throwable $throwable) {
            $snapshotBody = $this->brandedBodySnapshot($subject, $body, $variantLabel, $attachments);

            $this->storeLog(
                template: $template,
                notificationType: $notificationType,
                subject: $subject,
                body: $snapshotBody,
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
        $subjectUser = $this->normalizedTemplateContent((string) $template->subject_user, (string) $template->notification_type);
        $bodyUser = $this->normalizedTemplateContent((string) $template->body_user, (string) $template->notification_type);
        $subjectAdmin = $this->normalizedTemplateContent((string) $template->subject_admin, (string) $template->notification_type);
        $bodyAdmin = $this->normalizedTemplateContent((string) $template->body_admin, (string) $template->notification_type);

        if (filled($subjectUser) && filled($bodyUser)) {
            $deliveries[] = [
                'recipient_type' => 'test_user',
                'recipient_email' => $sendTo,
                'subject' => $this->renderStrict($subjectUser, $payload, 'user subject'),
                'body' => $this->renderStrict($bodyUser, $payload, 'user body'),
                'variant_label' => 'User Email',
            ];
        }

        if (filled($subjectAdmin) && filled($bodyAdmin)) {
            foreach ($this->parseRecipients($template->admin_recipients) as $recipient) {
                $deliveries[] = [
                    'recipient_type' => 'test_admin',
                    'recipient_email' => $recipient,
                    'subject' => $this->renderStrict($subjectAdmin, $payload, 'admin subject'),
                    'body' => $this->renderStrict($bodyAdmin, $payload, 'admin body'),
                    'variant_label' => 'Admin Email',
                ];
            }
        }

        $deliveries = collect($deliveries)
            ->unique(fn (array $delivery) => implode('|', [
                $delivery['recipient_type'],
                strtolower(trim($delivery['recipient_email'])),
                $delivery['subject'],
                $delivery['body'],
            ]))
            ->values()
            ->all();

        if ($deliveries !== []) {
            return $deliveries;
        }

        throw new RuntimeException(
            'The active template is incomplete. Fill at least one full email variant before sending a test.',
        );
    }

    private function brandedBodySnapshot(
        string $subject,
        string $body,
        string $variantLabel,
        array $attachments = [],
    ): string {
        return (new TemplatedNotificationMail(
            $subject,
            $body,
            $variantLabel,
            $attachments,
            $this->emailBrandingService->currentBrandingPayload(),
        ))->previewHtml();
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

    private function ensurePasswordChangeLinkBlock(
        string $renderedBody,
        string $templateBody,
        array $payload,
    ): string {
        $sections = [];

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

    private function sendPasswordResetForContext(
        User $user,
        string $resetUrl,
        string $otpCode,
        int $expiresInMinutes,
        string $referenceType,
    ): void {
        $template = $this->preparedTemplate(EmailNotificationTypeRegistry::RESET_PASSWORD);
        $payload = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'reset_url' => $resetUrl,
            'password_change_url' => $resetUrl,
            'otp' => $otpCode,
            'otp_code' => $otpCode,
            'reset_expiry_minutes' => (string) $expiresInMinutes,
            'login_url' => $this->studentLoginUrl(),
        ];

        $deliveries = $this->buildDeliveries($template, $payload);
        $deliveries = $this->excludeUserEmailFromAdminDeliveries($deliveries, $payload);

        foreach ($deliveries as $delivery) {
            $body = $delivery['body'];

            if ($delivery['recipient_type'] === 'user') {
                $body = $this->ensurePasswordChangeLinkBlock(
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
                referenceType: $referenceType,
                referenceId: $user->id,
                variantLabel: $delivery['variant_label'],
            );
        }
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
            'reset_url' => $this->publicRoute('password.reset', [
                'token' => 'sample-reset-token',
                'email' => $sendTo,
            ]),
            'otp' => '123456',
            'otp_code' => '123456',
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
            'workbook_file_name' => 'sample-workbook.pdf',
            'payment_reference' => 'PAY-SAMPLE-001',
            'amount' => '499.00',
            'enrollment_url' => $this->signedOnboardingRoute('onboarding.enrollment.show', [
                'onboardingState' => 999001,
            ]),
            'invoice_pdf_file_name' => 'online-confirmation-test-student.pdf',
            'signup_url' => $this->signedOnboardingRoute('onboarding.signup.show', [
                'onboardingState' => 999001,
            ]),
            'package_title' => 'Masterclass Standard',
            'tier_name' => 'Masterclass',
            'invoice_number' => 'INV-SAMPLE-001',
            'payment_amount' => '42.00',
            'currency_code' => 'USD',
            'installment_count' => '7',
            'installments_paid_count' => '3',
            'balance_due' => '126.00',
            'next_due_at' => now()->addMonth()->format('Y-m-15'),
            'grace_deadline_at' => now()->addMonth()->addDays(3)->format('Y-m-d'),
            'payment_completed_at' => now()->format('Y-m-d H:i'),
            'irregular_activity_count' => '3',
            'support_whatsapp' => '6281234567890',
            'support_whatsapp_url' => 'https://wa.me/6281234567890',
            'support_email' => 'support@yogafx.com',
            'support_email_url' => 'mailto:support@yogafx.com',
            'dashboard_url' => route('student.dashboard'),
            'login_url' => $this->studentLoginUrl(),
        ];

        $base['notification_type'] = $notificationType;

        return $base;
    }

    /**
     * @param  array<int, array{recipient_type: string, recipient_email: string, subject: string, body: string, variant_label: string}>  $deliveries
     * @return array<int, array{recipient_type: string, recipient_email: string, subject: string, body: string, variant_label: string}>
     */
    private function excludeUserEmailFromAdminDeliveries(array $deliveries, array $payload): array
    {
        $userEmail = strtolower(trim((string) ($payload['user_email'] ?? '')));

        if ($userEmail === '') {
            return $deliveries;
        }

        return collect($deliveries)
            ->reject(function (array $delivery) use ($userEmail): bool {
                return $delivery['recipient_type'] === 'admin'
                    && strtolower(trim($delivery['recipient_email'])) === $userEmail;
            })
            ->values()
            ->all();
    }

    private function normalizedTemplateContent(string $content, string $notificationType): string
    {
        if ($notificationType !== EmailNotificationTypeRegistry::SIGNUP || $content === '') {
            return $content;
        }

        return preg_replace(
            '/{{\s*continuation_url\s*}}|(?<!{){\s*continuation_url\s*}(?!})/',
            '{{ login_url }}',
            $content,
        ) ?? $content;
    }

    private function signupNotificationAlreadySent(User $user, ?OnboardingState $onboardingState = null): bool
    {
        return EmailLog::query()
            ->where('notification_type', EmailNotificationTypeRegistry::SIGNUP)
            ->where('recipient_type', 'user')
            ->where('status', 'sent')
            ->where(function ($query) use ($user, $onboardingState): void {
                $query->where(function ($userQuery) use ($user): void {
                    $userQuery->where('reference_type', 'user')
                        ->where('reference_id', $user->id);
                });

                if ($onboardingState instanceof OnboardingState) {
                    $query->orWhere(function ($onboardingQuery) use ($onboardingState): void {
                        $onboardingQuery->where('reference_type', 'onboarding_state')
                            ->where('reference_id', $onboardingState->id);
                    });
                }
            })
            ->exists();
    }

    private function notificationAlreadySent(string $notificationType, string $referenceType, int $referenceId): bool
    {
        return EmailLog::query()
            ->where('notification_type', $notificationType)
            ->where('reference_type', $referenceType)
            ->where('reference_id', $referenceId)
            ->where('status', 'sent')
            ->exists();
    }

    private function studentLoginUrl(): string
    {
        return PublicUrl::studentLogin();
    }

    private function latestStudentAccessAt(User $user)
    {
        if ($this->studentSessionSchemaReady()) {
            $latestSession = UserSession::query()
                ->where('user_id', $user->id)
                ->orderByDesc('last_activity_at')
                ->orderByDesc('logout_at')
                ->orderByDesc('login_at')
                ->first(['login_at', 'last_activity_at', 'logout_at']);

            $lastAccessAt = $latestSession?->last_activity_at
                ?? $latestSession?->logout_at
                ?? $latestSession?->login_at;

            if ($lastAccessAt) {
                return $lastAccessAt;
            }
        }

        return $user->created_at;
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

    private function publicRoute(string $routeName, array $parameters = []): string
    {
        $relativePath = URL::route($routeName, $parameters, false);

        return $this->publicAppUrl().$relativePath;
    }

    private function publicAppUrl(): string
    {
        return rtrim((string) config('app.public_url', config('app.url')), '/');
    }

    private function signedOnboardingRoute(string $routeName, array $parameters): string
    {
        return URL::temporarySignedRoute(
            $routeName,
            now()->addDays(7),
            $parameters,
        );
    }

    private function paymentSuccessUrl(Invoice $invoice, ?OnboardingState $onboardingState = null): string
    {
        if ($invoice->type === Invoice::TYPE_INITIAL && $onboardingState instanceof OnboardingState) {
            return $this->signedOnboardingRoute('onboarding.enrollment.show', [
                'onboardingState' => $onboardingState->id,
            ]);
        }

        if ($invoice->type === Invoice::TYPE_UPGRADE) {
            return URL::temporarySignedRoute(
                'student.upgrades.success',
                now()->addDays(7),
                ['invoice' => $invoice->id],
            );
        }

        return $this->studentLoginUrl();
    }
}
