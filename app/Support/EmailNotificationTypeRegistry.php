<?php

namespace App\Support;

class EmailNotificationTypeRegistry
{
    public const MODULE_COMPLETION = 'module_completion';
    public const ASSIGNMENT_REVIEW = 'assignment_review';
    public const ASSIGNMENT_APPROVED = 'assignment_approved';
    public const ASSIGNMENT_REJECTED = 'assignment_rejected';
    public const CERTIFICATE_CREATED = 'certificate_created';
    public const SIGNUP = 'signup';
    public const RESET_PASSWORD = 'reset_password';
    public const ASSESSMENT_COMPLETE = 'assessment_complete';
    public const COURSE_COMPLETE = 'course_complete';
    public const REMINDER = 'reminder';

    /**
     * @return array<int, array{
     *     value: string,
     *     label: string,
     *     description: string,
     *     trigger: string,
     *     merge_tags: array<int, string>
     * }>
     */
    public static function types(): array
    {
        return [
            [
                'value' => self::MODULE_COMPLETION,
                'label' => 'Module Completion',
                'description' => 'Send an operational update after a student completes every lesson in a module.',
                'trigger' => 'Triggered when a student completes all lessons inside one module.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ module_title }}',
                    '{{ completion_date }}',
                    '{{ module_progress }}',
                    '{{ course_progress }}',
                    '{{ study_time }}',
                ],
            ],
            [
                'value' => self::ASSIGNMENT_REVIEW,
                'label' => 'Assignments Review',
                'description' => 'Confirm that a submitted assignment has entered the review queue.',
                'trigger' => 'Triggered when a student submits an assignment or graduation video.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ assignment_type }}',
                    '{{ admin_email }}',
                ],
            ],
            [
                'value' => self::ASSIGNMENT_APPROVED,
                'label' => 'Assignments Approved',
                'description' => 'Notify the student and admins when an assignment is approved.',
                'trigger' => 'Triggered when an admin changes assignment status to approved.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ assignment_type }}',
                ],
            ],
            [
                'value' => self::ASSIGNMENT_REJECTED,
                'label' => 'Assignments Rejected',
                'description' => 'Send rejection feedback so the student knows a re-upload is required.',
                'trigger' => 'Triggered when an admin changes assignment status to rejected.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ assignment_type }}',
                    '{{ feedback }}',
                ],
            ],
            [
                'value' => self::CERTIFICATE_CREATED,
                'label' => 'Certificate Created',
                'description' => 'Announce that a certificate has been generated and is ready to access.',
                'trigger' => 'Triggered when an admin generates or re-sends a certificate notification.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ certificate_type }}',
                    '{{ certificate_file_name }}',
                ],
            ],
            [
                'value' => self::SIGNUP,
                'label' => 'Signup',
                'description' => 'Welcome new students and optionally inform the admin team about fresh registrations.',
                'trigger' => 'Triggered immediately after a user account is created successfully.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ admin_email }}',
                    '{{ access_tier }}',
                    '{{ access_tier_label }}',
                    '{{ registration_date }}',
                    '{{ dashboard_url }}',
                    '{{ login_url }}',
                ],
            ],
            [
                'value' => self::RESET_PASSWORD,
                'label' => 'Reset Password',
                'description' => 'Deliver a password reset link while optionally sending an admin copy of the request.',
                'trigger' => 'Triggered when a user requests a password reset link.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ reset_url }}',
                    '{{ reset_expiry_minutes }}',
                    '{{ login_url }}',
                ],
            ],
            [
                'value' => self::ASSESSMENT_COMPLETE,
                'label' => 'Assessment Complete',
                'description' => 'Confirm that an assessment attempt has been completed and scored.',
                'trigger' => 'Triggered when a student completes an assessment attempt.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ assessment_title }}',
                    '{{ assessment_score }}',
                    '{{ completed_at }}',
                    '{{ result_url }}',
                ],
            ],
            [
                'value' => self::COURSE_COMPLETE,
                'label' => 'Course Complete',
                'description' => 'Notify users when a full course or program milestone has been completed.',
                'trigger' => 'Triggered when a student completes the full course or learning program context.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ course_title }}',
                    '{{ completion_date }}',
                    '{{ course_progress }}',
                ],
            ],
            [
                'value' => self::REMINDER,
                'label' => 'Reminder',
                'description' => 'Re-engage students who have stopped working on lessons for one week.',
                'trigger' => 'Triggered by a scheduled reminder job when a student has no lesson activity for 7 days.',
                'merge_tags' => [
                    '{{ user_name }}',
                    '{{ user_email }}',
                    '{{ last_activity_date }}',
                    '{{ inactive_days }}',
                    '{{ dashboard_url }}',
                    '{{ login_url }}',
                ],
            ],
        ];
    }

    public static function isValid(string $notificationType): bool
    {
        return collect(self::types())
            ->contains(fn (array $type) => $type['value'] === $notificationType);
    }

    public static function labelFor(string $notificationType): string
    {
        return self::metadataFor($notificationType)['label']
            ?? str($notificationType)->replace('_', ' ')->title()->value();
    }

    /**
     * @return array<int, string>
     */
    public static function mergeTagsFor(string $notificationType): array
    {
        return self::metadataFor($notificationType)['merge_tags'] ?? [];
    }

    public static function descriptionFor(string $notificationType): string
    {
        return self::metadataFor($notificationType)['description'] ?? '';
    }

    public static function triggerFor(string $notificationType): string
    {
        return self::metadataFor($notificationType)['trigger'] ?? '';
    }

    /**
     * @return array{
     *     value?: string,
     *     label?: string,
     *     description?: string,
     *     trigger?: string,
     *     merge_tags?: array<int, string>
     * }
     */
    private static function metadataFor(string $notificationType): array
    {
        return collect(self::types())
            ->firstWhere('value', $notificationType) ?? [];
    }
}
