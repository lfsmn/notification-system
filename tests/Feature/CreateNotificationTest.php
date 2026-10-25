<?php

use App\Jobs\SendNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('creates a notification and queues it on its priority queue', function () {
    $this->postJson('/api/v1/notifications', [
        'channel' => 'sms',
        'recipient' => '+905551234567',
        'content' => 'Hello',
        'priority' => 'high',
    ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('data.priority', 'high');

    Queue::assertPushedOn('high', SendNotification::class);
});

it('rejects invalid input', function (array $payload, string $field) {
    $this->postJson('/api/v1/notifications', $payload)
        ->assertStatus(422)
        ->assertJsonValidationErrors($field);

    Queue::assertNothingPushed();
})->with([
    'unknown channel' => [['channel' => 'fax', 'recipient' => 'x', 'content' => 'Hi'], 'channel'],
    'invalid phone number' => [['channel' => 'sms', 'recipient' => '12345', 'content' => 'Hi'], 'recipient'],
    'invalid email' => [['channel' => 'email', 'recipient' => 'not-an-email', 'content' => 'Hi'], 'recipient'],
    'sms over 160 characters' => [['channel' => 'sms', 'recipient' => '+905551234567', 'content' => str_repeat('a', 161)], 'content'],
    'missing content' => [['channel' => 'sms', 'recipient' => '+905551234567'], 'content'],
    'scheduled in the past' => [['channel' => 'sms', 'recipient' => '+905551234567', 'content' => 'Hi', 'scheduled_at' => '2000-01-01T00:00:00Z'], 'scheduled_at'],
]);

it('does not create or queue a duplicate for the same idempotency key', function () {
    $payload = ['channel' => 'sms', 'recipient' => '+905551234567', 'content' => 'Hi', 'idempotency_key' => 'abc-1'];

    $first = $this->postJson('/api/v1/notifications', $payload)->assertStatus(202);
    $second = $this->postJson('/api/v1/notifications', $payload)->assertStatus(200);

    expect($second->json('data.id'))->toBe($first->json('data.id'))
        ->and(Notification::count())->toBe(1);

    Queue::assertPushed(SendNotification::class, 1);
});

it('stores scheduled notifications as pending in UTC without queueing them', function () {
    $localTime = now()->addDay()->setTimezone('Europe/Istanbul')->startOfHour();

    $this->postJson('/api/v1/notifications', [
        'channel' => 'sms',
        'recipient' => '+905551234567',
        'content' => 'Later',
        'scheduled_at' => $localTime->toIso8601String(),
    ])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.scheduled_at', $localTime->copy()->utc()->toIso8601String());

    Queue::assertNothingPushed();
});
