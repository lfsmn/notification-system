<?php

namespace App\Services;

use App\Enums\Channel;
use App\Enums\NotificationStatus;
use App\Enums\Priority;
use App\Models\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class MetricsService
{
    private const WINDOW_MINUTES = 60;

    public function snapshot(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'window_minutes' => self::WINDOW_MINUTES,
            'queue_depth' => $this->queueDepth(),
            'backlog' => $this->backlog(),
            'channels' => $this->deliveryStats(now()->subMinutes(self::WINDOW_MINUTES)),
        ];
    }

    private function queueDepth(): array
    {
        $depth = [];

        foreach (Priority::cases() as $priority) {
            $depth[$priority->value] = Queue::size($priority->value);
        }

        return $depth;
    }

    private function backlog(): array
    {
        $open = [NotificationStatus::Pending, NotificationStatus::Queued, NotificationStatus::Processing];

        $counts = Notification::query()
            ->whereIn('status', $open)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $backlog = [];

        foreach ($open as $status) {
            $backlog[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $backlog;
    }

    private function deliveryStats(Carbon $since): array
    {
        $latency = 'extract(epoch from (sent_at - coalesce(scheduled_at, created_at)))';

        $rows = DB::table('notifications')
            ->selectRaw("channel,
                count(*) filter (where status = 'sent') as sent,
                count(*) filter (where status = 'failed') as failed,
                avg($latency) filter (where status = 'sent') as avg_latency,
                percentile_cont(0.95) within group (order by $latency) filter (where status = 'sent') as p95_latency")
            ->where(fn ($query) => $query->where('sent_at', '>=', $since)->orWhere('failed_at', '>=', $since))
            ->groupBy('channel')
            ->get()
            ->keyBy('channel');

        $stats = [];

        foreach (Channel::cases() as $channel) {
            $row = $rows->get($channel->value);
            $sent = (int) ($row->sent ?? 0);
            $failed = (int) ($row->failed ?? 0);
            $total = $sent + $failed;

            $stats[$channel->value] = [
                'sent' => $sent,
                'failed' => $failed,
                'success_rate' => $total > 0 ? round($sent / $total, 4) : null,
                'failure_rate' => $total > 0 ? round($failed / $total, 4) : null,
                'avg_latency_ms' => isset($row->avg_latency) ? (int) round($row->avg_latency * 1000) : null,
                'p95_latency_ms' => isset($row->p95_latency) ? (int) round($row->p95_latency * 1000) : null,
            ];
        }

        return $stats;
    }
}
