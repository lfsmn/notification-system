<?php

use App\Jobs\SendNotification;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

function batchItem(array $overrides = []): array
{
    return [...['channel' => 'sms', 'recipient' => '+905551234567', 'content' => 'Hi'], ...$overrides];
}

it('creates every notification of a batch', function () {
    $response = $this->postJson('/api/v1/notifications/batch', [
        'notifications' => [
            batchItem(['priority' => 'high']),
            batchItem(['channel' => 'email', 'recipient' => 'a@example.com', 'priority' => 'low']),
        ],
    ]);

    $response->assertStatus(202)->assertJsonCount(2, 'data.notifications');

    expect(Notification::where('batch_id', $response->json('data.batch_id'))->count())->toBe(2);
    Queue::assertPushedOn('high', SendNotification::class);
    Queue::assertPushedOn('low', SendNotification::class);
});

it('rejects the whole batch when one item is invalid', function () {
    $this->postJson('/api/v1/notifications/batch', [
        'notifications' => [batchItem(), batchItem(['channel' => 'fax'])],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notifications.1.channel');

    expect(Notification::count())->toBe(0)
        ->and(NotificationBatch::count())->toBe(0);
});

it('rejects more than 1000 notifications', function () {
    $this->postJson('/api/v1/notifications/batch', [
        'notifications' => array_fill(0, 1001, batchItem()),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notifications');
});

it('rejects duplicate idempotency keys inside one batch', function () {
    $this->postJson('/api/v1/notifications/batch', [
        'notifications' => [batchItem(['idempotency_key' => 'same']), batchItem(['idempotency_key' => 'same'])],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('notifications.0.idempotency_key');
});

it('returns the original batch when the batch idempotency key is reused', function () {
    $payload = ['idempotency_key' => 'batch-1', 'notifications' => [batchItem(), batchItem()]];

    $first = $this->postJson('/api/v1/notifications/batch', $payload)->assertStatus(202);
    $second = $this->postJson('/api/v1/notifications/batch', $payload)->assertStatus(200);

    expect($second->json('data.batch_id'))->toBe($first->json('data.batch_id'))
        ->and($second->json('data.notifications'))->toHaveCount(2)
        ->and(Notification::count())->toBe(2);

    Queue::assertPushed(SendNotification::class, 2);
});