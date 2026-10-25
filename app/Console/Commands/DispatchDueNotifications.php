<?php

namespace App\Console\Commands;

use App\Enums\NotificationStatus;
use App\Jobs\SendNotification;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DispatchDueNotifications extends Command
{
    protected $signature = 'notifications:dispatch-due {--limit=1000}';
  
    protected $description = 'Queue scheduled notifications that are due and recover notifications stuck in processing';

    private const STUCK_AFTER_MINUTES = 5;

    public function handle(): int
    {
        $due = $this->dispatchDue((int) $this->option('limit'));
        $recovered = $this->recoverStuck();

        if ($due > 0 || $recovered > 0) {
            Log::info('Scheduler run', ['dispatched_due' => $due, 'recovered_stuck' => $recovered]);
        }

        $this->info("Dispatched {$due} due, recovered {$recovered} stuck.");

        return self::SUCCESS;
    }

    private function dispatchDue(int $limit): int
    {
        $claimed = DB::transaction(function () use ($limit) {
            $rows = Notification::query()
                ->where('status', NotificationStatus::Pending)
                ->where('scheduled_at', '<=', now())
                ->orderBy('scheduled_at')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get(['id', 'priority']);

            if ($rows->isNotEmpty()) {
                Notification::whereIn('id', $rows->pluck('id'))
                    ->update(['status' => NotificationStatus::Queued]);
            }

            return $rows;
        });

        foreach ($claimed as $notification) {
            SendNotification::dispatch($notification->id)
                ->onQueue($notification->priority->value);
        }

        return $claimed->count();
    }

    private function recoverStuck(): int
    {
        $threshold = now()->subMinutes(self::STUCK_AFTER_MINUTES);

        $stuck = Notification::query()
            ->where('status', NotificationStatus::Processing)
            ->where('updated_at', '<', $threshold)
            ->limit(500)
            ->get(['id', 'priority', 'attempts']);

        $recovered = 0;

        foreach ($stuck as $notification) {
            $giveUp = $notification->attempts >= SendNotification::MAX_ATTEMPTS;

            $updated = Notification::where('id', $notification->id)
                ->where('status', NotificationStatus::Processing)
                ->where('updated_at', '<', $threshold)
                ->update($giveUp
                    ? [
                        'status' => NotificationStatus::Failed,
                        'failed_at' => now(),
                        'last_error' => 'Worker interrupted during delivery',
                    ]
                    : [
                        'status' => NotificationStatus::Queued,
                        'last_error' => 'Worker interrupted during delivery, requeued',
                    ]);

            if ($updated === 1 && ! $giveUp) {
                SendNotification::dispatch($notification->id)
                    ->onQueue($notification->priority->value);
            }

            $recovered += $updated;
        }

        return $recovered;
    }
}