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
            'notifications.*.idempotency_key' => ['nullable', 'string', 'max:255', 'distinct'],
        ];

        $items = $this->input('notifications');

        if (is_array($items) && count($items) <= 1000) {
           foreach (array_keys($items) as $index) {
                $itemRules = StoreNotificationRequest::notificationRules(
                    $this->input("notifications.$index.channel"),
                    "notifications.$index.",
                );

                // Keep the wildcard rule for idempotency_key: an explicit per-item rule would override it and drop `distinct`
                unset($itemRules["notifications.$index.idempotency_key"]);

                $rules += $itemRules;
            }
        }

        return $rules;
    }
}