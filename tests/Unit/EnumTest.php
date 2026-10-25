<?php

use App\Enums\Channel;
use App\Enums\NotificationStatus;

it('allows cancelling only pending and queued notifications', function () {
    $cancellable = array_filter(NotificationStatus::cases(), fn ($status) => $status->isCancellable());

    expect(array_values($cancellable))->toBe([NotificationStatus::Pending, NotificationStatus::Queued]);
});

it('has a content limit for every channel', function () {
    expect(Channel::Sms->maxContentLength())->toBe(160);

    foreach (Channel::cases() as $channel) {
        expect($channel->maxContentLength())->toBeGreaterThan(0);
    }
});
