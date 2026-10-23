<?php

namespace App\Admission\Queries;

use App\Authorization\CapabilityAuthorizer;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Admission\ApplicationOpeningAlertReceipt;
use App\Models\Admission\ApplicationOpeningSubscription;
use App\Models\Foundation\Institution;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListApplicationOpeningSubscribersQuery
{
    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    public function allowed(User $actor, Institution $institution): bool
    {
        return (bool) config('application-opening-alerts.enabled', false)
            && $this->authorizer->decideFresh($actor, CapabilityCatalog::SETUP_INSTITUTION_MANAGE, [
                'institution_id' => (int) $institution->id,
                'office' => 'registrar',
            ])->allowed;
    }

    /** @param array{search?: ?string, status?: ?string, page?: int|string|null} $filters */
    public function handle(User $actor, Institution $institution, array $filters): LengthAwarePaginator
    {
        abort_unless($this->allowed($actor, $institution), 403);
        $query = ApplicationOpeningSubscription::query()
            ->where('institution_id', $institution->id)
            ->select(['id', 'email', 'state', 'created_at', 'verified_at', 'consumed_at', 'unsubscribed_at', 'expires_at'])
            ->addSelect(['delivery_status' => ApplicationOpeningAlertReceipt::query()
                ->select('notification_deliveries.status')
                ->join('notification_deliveries', 'notification_deliveries.id', '=', 'application_opening_alert_receipts.delivery_id')
                ->whereColumn('application_opening_alert_receipts.subscription_id', 'application_opening_subscriptions.id')
                ->whereColumn('application_opening_alert_receipts.generation', 'application_opening_subscriptions.generation')
                ->orderByDesc('application_opening_alert_receipts.id')
                ->limit(1)]);
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $literal = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($search));
            $query->whereRaw("normalized_email LIKE ? ESCAPE '\\'", ['%'.$literal.'%']);
        }
        if (($filters['status'] ?? '') !== '') {
            $query->where('state', $filters['status']);
        }
        $rows = $query->orderByDesc('id')->paginate(15, ['*'], 'page', (int) ($filters['page'] ?? 1));
        $rows->through(fn (ApplicationOpeningSubscription $row): array => [
            'id' => $row->id,
            'email' => $row->email,
            'state' => $row->state,
            'delivery_status' => $row->getAttribute('delivery_status'),
            'created_at' => $row->created_at?->toIso8601String(),
            'verified_at' => $row->verified_at?->toIso8601String(),
            'consumed_at' => $row->consumed_at?->toIso8601String(),
            'unsubscribed_at' => $row->unsubscribed_at?->toIso8601String(),
            'expires_at' => $row->expires_at?->toIso8601String(),
        ]);
        $this->audit->handle('admission.opening_alert_signups_viewed', [
            'institution_id' => (int) $institution->id,
            'capability' => CapabilityCatalog::SETUP_INSTITUTION_MANAGE,
            'office' => 'registrar',
            'page' => $rows->currentPage(),
            'filtered' => $search !== '' || ($filters['status'] ?? '') !== '',
            'returned_subscription_ids' => $rows->getCollection()->pluck('id')->all(),
        ], (string) $actor->id);

        return $rows;
    }
}
