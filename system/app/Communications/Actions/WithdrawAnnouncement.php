<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\AnnouncementSource;
use App\Communications\AnnouncementSourceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\Announcement;
use App\Models\Communications\AnnouncementRevision;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class WithdrawAnnouncement
{
    public function __construct(
        private readonly AnnouncementCommandSupport $commands,
        private readonly AnnouncementSourceAuthorizer $sources,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, Announcement $announcement, array $data): Announcement
    {
        $validated = Validator::make($data, [
            'request_id' => ['required', 'uuid'],
            'expected_revision' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ])->validate();
        $reason = trim($validated['reason']);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => 'Withdrawal reason must be between 3 and 500 characters.']);
        }

        return $this->commands->run(
            $actor,
            (string) $validated['request_id'],
            'withdraw',
            (int) $announcement->getKey(),
            (int) $validated['expected_revision'],
            $announcement->publisher_key,
            ['reason' => $reason],
            function (User $freshActor, AnnouncementSource $source, ?Announcement $locked) use ($reason): Announcement {
                if ($locked === null || $locked->status !== 'published') {
                    abort(409, 'Only a published announcement can be withdrawn.');
                }
                $this->sources->assertCanPublish($freshActor, $locked->institution_id, $locked->publisher_key);
                $locked->forceFill([
                    'status' => 'withdrawn',
                    'withdrawn_at' => now()->utc(),
                    'withdrawn_by' => $freshActor->getKey(),
                    'withdrawal_reason' => $reason,
                    'revision' => $locked->revision + 1,
                ])->save();
                $announcement = $locked->refresh();
                AnnouncementRevision::query()->create([
                    'announcement_id' => $announcement->getKey(),
                    'program_id' => $announcement->program_id,
                    'revision' => $announcement->revision,
                    'publisher_key' => $announcement->publisher_key,
                    'publisher_label' => $announcement->publisher_label,
                    'title' => $announcement->title,
                    'summary' => $announcement->summary,
                    'body' => $announcement->body,
                    'priority' => $announcement->priority,
                    'is_featured' => $announcement->is_featured,
                    'status' => $announcement->status,
                    'starts_at' => $announcement->starts_at,
                    'ends_at' => $announcement->ends_at,
                    'published_at' => $announcement->published_at,
                    'actor_id' => $freshActor->getKey(),
                    'actor_name' => $freshActor->name,
                    'reason' => $reason,
                    'created_at' => now()->utc(),
                ]);
                $this->audit->handle('communications.announcement_withdrawn', [
                    'announcement_id' => $announcement->getKey(),
                    'revision' => $announcement->revision,
                    'publisher_key' => $announcement->publisher_key,
                    'reason' => $reason,
                ], (string) $freshActor->getKey());

                return $announcement;
            },
            true,
        );
    }
}
