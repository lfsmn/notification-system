<?php

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Support\Str;

it('shows a notification', function () {
    $notification = Notification::factory()->create();

    $this->getJson("/api/v1/notifications/{$notification->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $notification->id);
});

it('returns 404 for unknown and malformed ids', function () {
    $this->getJson('/api/v1/notifications/abc')->assertNotFound();
    $this->getJson('/api/v1/notifications/'.Str::uuid())->assertNotFound();
});

it('filters the list by status and channel', function () {
    Notification::factory()->count(2)->create(['status' => NotificationStatus::Sent]);
    Notification::factory()->create(['status' => NotificationStatus::Failed]);
    Notification::factory()->create([
        'status' => NotificationStatus::Sent,
        'channel' => Channel::Email,
        'recipient' => 'a@example.com',
    ]);

    $this->getJson('/api/v1/notifications?status=sent&channel=sms')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 2);
});

it('summarises the status of a batch', function () {
    $batch = NotificationBatch::create(['total_count' => 3]);
    Notification::factory()->count(2)->create(['batch_id' => $batch->id, 'status' => NotificationStatus::Sent]);
    Notification::factory()->create(['batch_id' => $batch->id, 'status' => NotificationStatus::Failed]);

    $this->getJson("/api/v1/batches/{$batch->id}")
        ->assertOk()
        ->assertJsonPath('data.status_counts.sent', 2)
        ->assertJsonPath('data.status_counts.failed', 1)
        ->assertJsonPath('data.status_counts.queued', 0);
});

it('cancels a queued notification', function () {
    $notification = Notification::factory()->create();

    $this->postJson("/api/v1/notifications/{$notification->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'cancelled');
});

it('refuses to cancel a notification that was already sent', function () {
    $notification = Notification::factory()->create(['status' => NotificationStatus::Sent]);

    $this->postJson("/api/v1/notifications/{$notification->id}/cancel")->assertStatus(409);

    expect($notification->refresh()->status)->toBe(NotificationStatus::Sent);
});