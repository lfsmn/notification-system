<?php

namespace App\Http\Requests;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListNotificationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(NotificationStatus::class)],
            'channel' => ['sometimes', Rule::enum(Channel::class)],
            'batch_id' => ['sometimes', 'uuid'],
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}