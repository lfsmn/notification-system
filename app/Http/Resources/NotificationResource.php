<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'idempotency_key' => $this->idempotency_key,
            'batch_id' => $this->batch_id,
            'channel' => $this->channel,
            'recipient' => $this->recipient,
            'content' => $this->content,
            'priority' => $this->priority,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'provider_message_id' => $this->provider_message_id,
            'last_error' => $this->last_error,
            'scheduled_at' => $this->scheduled_at?->toIso8601String(),
            'sent_at' => $this->sent_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
