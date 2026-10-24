<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Enums\Channel;
use App\Enums\Priority;

class StoreNotificationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $input = $this->input('channel');
        $channel = is_string($input) ? Channel::tryFrom($input) : null;

        return [
            'channel' => ['required', Rule::enum(Channel::class)],
            'recipient' => ['required', 'string', 'max:255', ...($channel?->recipientRules() ?? [])],
            'content' => ['required', 'string', 'max:' . ($channel?->maxContentLength() ?? 10000)],
            'priority' => ['sometimes', Rule::enum(Priority::class)],
            'scheduled_at' => ['nullable', 'date', 'after:now'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
