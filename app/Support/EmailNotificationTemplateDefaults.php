<?php

namespace App\Support;

class EmailNotificationTemplateDefaults
{
    /**
     * @return array{
     *     subject_user?: string,
     *     body_user?: string,
     *     subject_admin?: string,
     *     body_admin?: string,
     *     auto_enable?: bool
     * }
     */
    public static function for(string $notificationType): array
    {
        return match ($notificationType) {
            EmailNotificationTypeRegistry::SIGNUP => [
                'subject_user' => 'Welcome to YogaFX, {user_name}',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>Welcome to YogaFX. Your payment is complete and your account continuation is ready.</p>',
                    '<p>Your access tier: <strong>{access_tier_label}</strong></p>',
                    '<p>Continue your enrollment here: <a href="{continuation_url}">{continuation_url}</a></p>',
                    '<p>After enrollment and final password creation, you will enter your dashboard normally.</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::RESET_PASSWORD => [
                'subject_user' => 'Reset your YogaFX password',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>We received a request to reset your YogaFX password.</p>',
                    '<p><a href="{reset_url}">Click here to reset your password</a></p>',
                    '<p>This link will expire in {reset_expiry_minutes} minutes.</p>',
                    '<p>If you did not request this, you can safely ignore this email.</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::MODULE_COMPLETION => [
                'subject_user' => 'Module completed: {module_title}',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>You have completed <strong>{module_title}</strong>.</p>',
                    '<p>Module progress: {module_progress}</p>',
                    '<p>Current learning path progress: {course_progress}</p>',
                    '<p>Keep going from your dashboard: <a href="{dashboard_url}">{dashboard_url}</a></p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::ASSESSMENT_COMPLETE => [
                'subject_user' => 'Assessment completed: {assessment_title}',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>You have completed <strong>{assessment_title}</strong>.</p>',
                    '<p>Your latest score: <strong>{assessment_score}</strong></p>',
                    '<p>See your progress here: <a href="{result_url}">{result_url}</a></p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::ASSIGNMENT_REVIEW => [
                'subject_user' => 'Your assignment is under review',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>Your assignment submission for <strong>{assignment_type}</strong> has been received.</p>',
                    '<p>Our team is reviewing it now. We will email you again once the review is complete.</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::ASSIGNMENT_APPROVED => [
                'subject_user' => 'Your YogaFX graduation requirements are complete',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>Congratulations! You have completed all graduation requirements.</p>',
                    '<p>You can access your certificate from your dashboard: <a href="{dashboard_url}">{dashboard_url}</a></p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::ASSIGNMENT_REJECTED => [
                'subject_user' => 'Assignment update: resubmission needed',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>Your submission for <strong>{assignment_type}</strong> needs revision before it can be approved.</p>',
                    '<p>Feedback: {feedback}</p>',
                    '<p>Please revisit your dashboard and upload an updated version when ready.</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::CERTIFICATE_CREATED => [
                'subject_user' => 'Your YogaFX certificate is ready',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>Your certificate for <strong>{certificate_type}</strong> has been created.</p>',
                    '<p>Certificate file: {certificate_file_name}</p>',
                    '<p>You can check your student dashboard for the latest status.</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::COURSE_COMPLETE => [
                'subject_user' => 'Learning path complete: {course_title}',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>You have completed your accessible YogaFX learning path: <strong>{course_title}</strong>.</p>',
                    '<p>Completion date: {completion_date}</p>',
                    '<p>Learning path progress: {course_progress}</p>',
                    '<p>Open your dashboard here: <a href="{dashboard_url}">{dashboard_url}</a></p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::REMINDER => [
                'subject_user' => 'We saved your place in YogaFX',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>We noticed you have not continued your YogaFX learning session since {last_activity_date}.</p>',
                    '<p>Return to your dashboard anytime: <a href="{dashboard_url}">{dashboard_url}</a></p>',
                ]),
                'auto_enable' => true,
            ],
            default => [],
        };
    }
}
