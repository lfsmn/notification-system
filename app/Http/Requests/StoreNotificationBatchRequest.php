<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNotificationBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'notifications' => ['required', 'array', 'min:1', 'max:1000'],
            'notifications.*' => ['required', 'array'],
            'notifications.*.idempotency_key' => ['distinct'],
        ];

        $items = $this->input('notifications');

        if (is_array($items) && count($items) <= 1000) {
            foreach (array_keys($items) as $index) {
                $rules += StoreNotificationRequest::notificationRules($this->input("notifications.$index.channel"), "notifications.$index.");
            }
        }

        return $rules;
    }
}