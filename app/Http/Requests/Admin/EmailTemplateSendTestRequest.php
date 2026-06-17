<?php

namespace App\Http\Requests\Admin;

use App\Support\EmailNotificationTypeRegistry;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class EmailTemplateSendTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    public function rules(): array
    {
        return [
            'notification_type' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) {
                if (! is_string($value) || ! EmailNotificationTypeRegistry::isValid($value)) {
                    $fail('Notification type is invalid.');
                }
            }],
            'send_to' => ['required', 'email:rfc'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
        ];
    }
}