<?php

use App\Enums\NotificationStatus;
use App\Jobs\SendNotification;
use App\Models\Notification;
use App\Services\NotificationProviderClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    config(['services.notification_provider.url' => 'https://provider.test/send']);
    Queue::fake();
});

function runJob(Notification $notification): void
{
    (new SendNotification($notification->id))->handle(app(NotificationProviderClient::class));
}

it('marks the notification as sent with the provider message id', function () {
    Http::fake(['*' => Http::response(['messageId' => 'msg-1', 'status' => 'accepted'], 202)]);
    $notification = Notification::factory()->create();

    runJob($notification);
    $notification->refresh();

    expect($notification->status)->toBe(NotificationStatus::Sent)
        ->and($notification->provider_message_id)->toBe('msg-1')
        ->and($notification->attempts)->toBe(1)
        ->and($notification->sent_at)->not->toBeNull();

    Http::assertSent(fn ($request) => $request['to'] === $notification->recipient
        && $request['channel'] === 'sms'
        && $request->hasHeader('Idempotency-Key', $notification->id));
});

it('schedules a retry on a temporary provider error', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $notification = Notification::factory()->create();

    runJob($notification);
    $notification->refresh();

    expect($notification->status)->toBe(NotificationStatus::Queued)
        ->and($notification->attempts)->toBe(1)
        ->and($notification->last_error)->toBe('Provider returned HTTP 500');

    Queue::assertPushedOn('normal', SendNotification::class);
});

it('fails immediately on a permanent provider error', function () {
    Http::fake(['*' => Http::response([], 400)]);
    $notification = Notification::factory()->create();

    runJob($notification);
    $notification->refresh();

    expect($notification->status)->toBe(NotificationStatus::Failed)
        ->and($notification->failed_at)->not->toBeNull();

    Queue::assertNothingPushed();
});

it('gives up after the maximum number of attempts', function () {
    Http::fake(['*' => Http::response([], 500)]);
    $notification = Notification::factory()->create(['attempts' => SendNotification::MAX_ATTEMPTS - 1]);

    runJob($notification);
    $notification->refresh();

    expect($notification->status)->toBe(NotificationStatus::Failed)
        ->and($notification->attempts)->toBe(SendNotification::MAX_ATTEMPTS);

    Queue::assertNothingPushed();
});

it('does not send a cancelled notification', function () {
    Http::fake();
    $notification = Notification::factory()->create(['status' => NotificationStatus::Cancelled]);

    runJob($notification);

    Http::assertNothingSent();
    expect($notification->refresh()->status)->toBe(NotificationStatus::Cancelled);
});

it('delays a notification without using an attempt when the rate limit is reached', function () {
    $this->freezeTime();
    config(['services.notification_provider.rate_limit_per_second' => 1]);
    Http::fake(['*' => Http::response(['messageId' => 'msg-1'], 202)]);
    [$first, $second] = Notification::factory()->count(2)->create()->all();

    runJob($first);
    runJob($second);

    expect($first->refresh()->status)->toBe(NotificationStatus::Sent)
        ->and($second->refresh()->status)->toBe(NotificationStatus::Queued)
        ->and($second->attempts)->toBe(0);

    Queue::assertPushed(SendNotification::class, 1);
});