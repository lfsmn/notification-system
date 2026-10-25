<?php

namespace Database\Factories;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'channel' => Channel::Sms,
            'recipient' => '+90555'.fake()->numerify('#######'),
            'content' => fake()->sentence(),
            'priority' => Priority::Normal,
            'status' => NotificationStatus::Queued,
        ];
    }
}
