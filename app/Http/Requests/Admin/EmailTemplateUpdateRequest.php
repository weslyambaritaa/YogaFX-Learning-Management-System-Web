<?php

namespace App\Http\Requests\Admin;

use App\Support\EmailNotificationTypeRegistry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class EmailTemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'is_enabled' => ['required', 'boolean'],
            'admin_recipients' => ['nullable', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_string($value) || trim($value) === '') {
                    return;
                }

                foreach (preg_split('/[\s,;]+/', $value) ?: [] as $email) {
                    if ($email === '') {
                        continue;
                    }

                    if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail('Admin recipients must contain valid email addresses.');

                        return;
                    }
                }
            }],
            'subject_admin' => ['nullable', 'string', 'max:255'],
            'body_admin' => ['nullable', 'string'],
            'subject_user' => ['nullable', 'string', 'max:255'],
            'body_user' => ['nullable', 'string'],
            'notification_type' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_string($value) || ! EmailNotificationTypeRegistry::isValid($value)) {
                    $fail('Notification type is invalid.');
                }
            }],
        ];
    }
}
