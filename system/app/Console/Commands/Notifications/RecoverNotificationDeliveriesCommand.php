<?php

namespace App\Console\Commands\Notifications;

use App\Jobs\Notifications\ProcessNotificationDelivery;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Notifications\NotificationDelivery;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class RecoverNotificationDeliveriesCommand extends Command
{
    protected $signature = 'notifications:recover-deliveries {--limit=100} {--opening-alerts-only : Recover only anonymous application-opening mail}';

    protected $description = 'Requeue notification deliveries with due retries or expired processing leases';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $now = now();

        $deliveries = NotificationDelivery::query();
        if ($this->option('opening-alerts-only')) {
            $deliveries->where('recipient_type', ApplicationOpeningSubscription::class)
                ->whereIn('event_type', ['application.opening_alert.verify.v1', 'application.opening_alert.opened.v1'])
                ->where('channel', NotificationDelivery::CHANNEL_MAIL);
        }

        $deliveryIds = $deliveries
            ->where(function (Builder $query) use ($now): void {
                $query->where(function (Builder $query) use ($now): void {
                    $query
                        ->whereIn('status', [
                            NotificationDelivery::STATUS_PENDING,
                            NotificationDelivery::STATUS_RETRYING,
                        ])
                        ->where(function (Builder $query) use ($now): void {
                            $query
                                ->whereNull('next_attempt_at')
                                ->orWhere('next_attempt_at', '<=', $now);
                        });
                })->orWhere(function (Builder $query) use ($now): void {
                    $query
                        ->where('status', NotificationDelivery::STATUS_PROCESSING)
                        ->where(function (Builder $query) use ($now): void {
                            $query
                                ->whereNull('lease_expires_at')
                                ->orWhere('lease_expires_at', '<=', $now);
                        });
                });
            })
            ->orderBy('created_at')
            ->limit($limit)
            ->pluck('id');

        foreach ($deliveryIds as $deliveryId) {
            ProcessNotificationDelivery::dispatch((string) $deliveryId);
        }

        $this->info("Queued {$deliveryIds->count()} due notification delivery job(s).");

        return self::SUCCESS;
    }
}
