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
                    '<p>Your one-time password code: <strong>{otp}</strong></p>',
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
            EmailNotificationTypeRegistry::WORKBOOK_SENT => [
                'subject_user' => 'Your workbook is ready: {lesson_title}',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>Your workbook for <strong>{lesson_title}</strong> is attached to this email.</p>',
                    '<p>Module: {module_title}</p>',
                    '<p>Workbook file: {workbook_file_name}</p>',
                    '<p>You can continue learning from your dashboard here: <a href="{dashboard_url}">{dashboard_url}</a></p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_SUCCESS => [
                'subject_admin' => 'Installment payment success for {user_email}',
                'body_admin' => implode('', [
                    '<p>A successful installment payment has been recorded.</p>',
                    '<p>Student: <strong>{user_name}</strong> ({user_email})</p>',
                    '<p>Package: <strong>{package_title}</strong></p>',
                    '<p>Tier: <strong>{tier_name}</strong></p>',
                    '<p>Invoice: <strong>{invoice_number}</strong></p>',
                    '<p>Payment amount: <strong>{currency_code} {payment_amount}</strong></p>',
                    '<p>Installments paid: {installments_paid_count} of {installment_count}</p>',
                    '<p>Remaining balance: <strong>{currency_code} {balance_due}</strong></p>',
                    '<p>Next due date: {next_due_at}</p>',
                    '<p>Grace deadline: {grace_deadline_at}</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_FAILED => [
                'subject_admin' => 'Installment payment failed for {user_email}',
                'body_admin' => implode('', [
                    '<p>An installment payment failed and the subscription is now in grace period.</p>',
                    '<p>Student: <strong>{user_name}</strong> ({user_email})</p>',
                    '<p>Package: <strong>{package_title}</strong></p>',
                    '<p>Tier: <strong>{tier_name}</strong></p>',
                    '<p>Invoice: <strong>{invoice_number}</strong></p>',
                    '<p>Expected payment amount: <strong>{currency_code} {payment_amount}</strong></p>',
                    '<p>Installments paid: {installments_paid_count} of {installment_count}</p>',
                    '<p>Remaining balance: <strong>{currency_code} {balance_due}</strong></p>',
                    '<p>Next due date: {next_due_at}</p>',
                    '<p>Grace deadline: {grace_deadline_at}</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::INSTALLMENT_OVERDUE_INACTIVE => [
                'subject_admin' => 'Installment overdue: account inactive for {user_email}',
                'body_admin' => implode('', [
                    '<p>The installment grace deadline has passed and the student account is now inactive.</p>',
                    '<p>Student: <strong>{user_name}</strong> ({user_email})</p>',
                    '<p>Package: <strong>{package_title}</strong></p>',
                    '<p>Tier: <strong>{tier_name}</strong></p>',
                    '<p>Invoice: <strong>{invoice_number}</strong></p>',
                    '<p>Remaining balance: <strong>{currency_code} {balance_due}</strong></p>',
                    '<p>Next due date: {next_due_at}</p>',
                    '<p>Grace deadline: {grace_deadline_at}</p>',
                ]),
                'auto_enable' => true,
            ],
            EmailNotificationTypeRegistry::INSTALLMENT_PAYMENT_COMPLETED => [
                'subject_user' => 'Your installment payment is complete',
                'body_user' => implode('', [
                    '<p>Hi {user_name},</p>',
                    '<p>Your installment plan for <strong>{package_title}</strong> has been fully paid.</p>',
                    '<p>Invoice: <strong>{invoice_number}</strong></p>',
                    '<p>Total installments paid: {installments_paid_count} of {installment_count}</p>',
                    '<p>Completion date: {payment_completed_at}</p>',
                    '<p>Your access remains available in your YogaFX account.</p>',
                ]),
                'subject_admin' => 'Installment fully paid for {user_email}',
                'body_admin' => implode('', [
                    '<p>An installment plan has been fully paid.</p>',
                    '<p>Student: <strong>{user_name}</strong> ({user_email})</p>',
                    '<p>Package: <strong>{package_title}</strong></p>',
                    '<p>Tier: <strong>{tier_name}</strong></p>',
                    '<p>Invoice: <strong>{invoice_number}</strong></p>',
                    '<p>Final payment amount: <strong>{currency_code} {payment_amount}</strong></p>',
                    '<p>Total installments paid: {installments_paid_count} of {installment_count}</p>',
                    '<p>Completed at: {payment_completed_at}</p>',
                ]),
                'auto_enable' => true,
            ],
            default => [],
        };
    }
}
