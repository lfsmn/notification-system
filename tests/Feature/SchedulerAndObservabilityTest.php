<?php

use App\Enums\NotificationStatus;
use App\Jobs\SendNotification;
use App\Models\Notification;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => Queue::fake());

it('queues scheduled notifications that are due', function () {
    $due = Notification::factory()->create(['status' => NotificationStatus::Pending, 'scheduled_at' => now()->subMinute()]);
    $future = Notification::factory()->create(['status' => NotificationStatus::Pending, 'scheduled_at' => now()->addHour()]);

    $this->artisan('notifications:dispatch-due')->assertSuccessful();

    expect($due->refresh()->status)->toBe(NotificationStatus::Queued)
        ->and($future->refresh()->status)->toBe(NotificationStatus::Pending);

    Queue::assertPushed(SendNotification::class, 1);
});

it('requeues notifications stuck in processing', function () {
    $stuck = Notification::factory()->create([
        'status' => NotificationStatus::Processing,
        'attempts' => 1,
        'updated_at' => now()->subMinutes(10),
    ]);
    $active = Notification::factory()->create(['status' => NotificationStatus::Processing, 'attempts' => 1]);

    $this->artisan('notifications:dispatch-due')->assertSuccessful();

    expect($stuck->refresh()->status)->toBe(NotificationStatus::Queued)
        ->and($active->refresh()->status)->toBe(NotificationStatus::Processing);

    Queue::assertPushed(SendNotification::class, 1);
});

it('reports delivery metrics per channel', function () {
    Notification::factory()->create([
        'status' => NotificationStatus::Sent,
        'created_at' => now()->subSeconds(10),
        'sent_at' => now(),
    ]);
    Notification::factory()->create(['status' => NotificationStatus::Failed, 'failed_at' => now()]);

    $this->getJson('/api/v1/metrics')
        ->assertOk()
        ->assertJsonPath('channels.sms.sent', 1)
        ->assertJsonPath('channels.sms.failed', 1)
        ->assertJsonPath('channels.sms.success_rate', 0.5)
        ->assertJsonPath('channels.sms.avg_latency_ms', 10000);
});

it('reports healthy dependencies', function () {
    $this->getJson('/api/v1/health')
        ->assertOk()
        ->assertJsonPath('checks.database', 'up')
        ->assertJsonPath('checks.redis', 'up');
});

it('echoes a valid correlation id and replaces an invalid one', function () {
    $this->getJson('/api/v1/health', ['X-Correlation-ID' => 'trace-123'])
        ->assertHeader('X-Correlation-ID', 'trace-123');

    $response = $this->getJson('/api/v1/health', ['X-Correlation-ID' => 'not valid <script>']);

    expect($response->headers->get('X-Correlation-ID'))->not->toBe('not valid <script>');
});