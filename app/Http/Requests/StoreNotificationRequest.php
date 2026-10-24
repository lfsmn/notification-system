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
        return self::notificationRules($this->input('channel'));
    }

    public static function notificationRules(mixed $channelInput, string $prefix = ''): array
    {
        $channel = is_string($channelInput) ? Channel::tryFrom($channelInput) : null;

        return [
            $prefix.'channel' => ['required', Rule::enum(Channel::class)],
            $prefix.'recipient' => ['required', 'string', 'max:255', ...($channel?->recipientRules() ?? [])],
            $prefix.'content' => ['required', 'string', 'max:'.($channel?->maxContentLength() ?? 10000)],
            $prefix.'priority' => ['sometimes', Rule::enum(Priority::class)],
            $prefix.'scheduled_at' => ['nullable', 'date', 'after:now'],
            $prefix.'idempotency_key' => ['nullable', 'string', 'max:255'],
        ];
    }
}
