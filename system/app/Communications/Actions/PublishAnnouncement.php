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

final class PublishAnnouncement
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
        ])->validate();

        return $this->commands->run(
            $actor,
            (string) $validated['request_id'],
            'publish',
            (int) $announcement->getKey(),
            (int) $validated['expected_revision'],
            $announcement->publisher_key,
            [],
            function (User $freshActor, AnnouncementSource $source, ?Announcement $locked): Announcement {
                if ($locked === null || $locked->status !== 'draft') {
                    abort(409, 'Only a saved draft can be published.');
                }
                $this->sources->assertCanPublish($freshActor, $locked->institution_id, $locked->publisher_key);
                $now = now()->utc();
                $startsAt = $locked->starts_at ?? $now;
                if ($locked->ends_at !== null && ! $locked->ends_at->isAfter($startsAt)) {
                    throw ValidationException::withMessages(['ends_at' => 'End time must be later than the start time.']);
                }
                if ($locked->ends_at !== null && ! $locked->ends_at->isAfter($now)) {
                    throw ValidationException::withMessages(['ends_at' => 'An announcement cannot be published with an expired end time.']);
                }

                $locked->forceFill([
                    'status' => 'published',
                    'starts_at' => $startsAt->utc(),
                    'published_at' => $now,
                    'published_by' => $freshActor->getKey(),
                    'revision' => $locked->revision + 1,
                ])->save();
                $announcement = $locked->refresh();
                $this->recordRevision($announcement, $freshActor, 'Published');
                $this->audit->handle('communications.announcement_published', [
                    'announcement_id' => $announcement->getKey(),
                    'revision' => $announcement->revision,
                    'publisher_key' => $announcement->publisher_key,
                    'starts_at' => $announcement->starts_at?->toISOString(),
                    'ends_at' => $announcement->ends_at?->toISOString(),
                ], (string) $freshActor->getKey());

                return $announcement;
            },
            true,
        );
    }

    private function recordRevision(Announcement $announcement, User $actor, string $reason): void
    {
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
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'reason' => $reason,
            'created_at' => now(),
        ]);
    }
}
