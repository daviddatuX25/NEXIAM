<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\AnnouncementSource;
use App\Communications\AnnouncementSourceAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\Announcement;
use App\Models\Communications\AnnouncementRevision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class SaveAnnouncement
{
    public function __construct(
        private readonly AnnouncementCommandSupport $commands,
        private readonly AnnouncementSourceAuthorizer $sources,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): Announcement
    {
        $creating = ! isset($data['announcement_id']);
        $validated = Validator::make($data, [
            'publisher_key' => [$creating ? 'required' : 'prohibited', 'string', 'max:100'],
            'program_id' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:1', Rule::requiredIf($creating && str_starts_with((string) ($data['publisher_key'] ?? ''), 'program:'))],
            'request_id' => ['required', 'uuid'],
            'expected_revision' => [$creating ? 'prohibited' : 'required', 'integer', 'min:1'],
            'title' => ['required', 'string', 'min:1', 'max:80'],
            'summary' => ['required', 'string', 'min:1', 'max:160'],
            'body' => ['required', 'string', 'min:1', 'max:10000'],
            'priority' => ['required', 'in:normal,urgent'],
            'is_featured' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'announcement_id' => ['sometimes', 'required', 'integer', 'min:1'],
        ])->validate();

        $title = trim($validated['title']);
        $summary = trim($validated['summary']);
        if ($title === '') {
            throw ValidationException::withMessages(['title' => 'A title is required.']);
        }
        if ($summary === '') {
            throw ValidationException::withMessages(['summary' => 'A summary is required.']);
        }

        $startsAt = filled($validated['starts_at'] ?? null) ? Carbon::parse($validated['starts_at'])->utc() : null;
        $endsAt = filled($validated['ends_at'] ?? null) ? Carbon::parse($validated['ends_at'])->utc() : null;

        $announcementId = isset($validated['announcement_id']) ? (int) $validated['announcement_id'] : null;
        $expectedRevision = isset($validated['expected_revision']) ? (int) $validated['expected_revision'] : null;
        if (($announcementId === null) !== ($expectedRevision === null)) {
            throw ValidationException::withMessages(['expected_revision' => 'A current revision is required when editing an announcement.']);
        }
        $publisherKey = $announcementId === null ? (string) $validated['publisher_key'] : '';
        $programId = $announcementId === null ? (isset($validated['program_id']) ? (int) $validated['program_id'] : null) : null;
        $payload = [
            'program_id' => $programId,
            'title' => $title,
            'summary' => $summary,
            'body' => $validated['body'],
            'priority' => $validated['priority'],
            'is_featured' => (bool) $validated['is_featured'],
            'starts_at' => $startsAt?->toISOString(),
            'ends_at' => $endsAt?->toISOString(),
        ];

        return $this->commands->run(
            $actor,
            (string) $validated['request_id'],
            'save',
            $announcementId,
            $expectedRevision,
            $publisherKey,
            $payload,
            function (User $freshActor, AnnouncementSource $source, ?Announcement $locked) use ($payload): Announcement {
                $targetProgram = $this->sources->assertCanTarget(
                    $freshActor,
                    $source,
                    $locked === null ? $payload['program_id'] : ($locked->program_id === null ? null : (int) $locked->program_id),
                );
                if ($locked !== null && $locked->status !== 'draft') {
                    if ($locked->status !== 'published') {
                        abort(409, 'Withdrawn announcements cannot be edited.');
                    }
                    $this->sources->assertCanPublish($freshActor, $locked->institution_id, $locked->publisher_key);
                }

                $effectiveStartsAt = $payload['starts_at'] !== null
                    ? Carbon::parse($payload['starts_at'])->utc()
                    : ($locked?->status === 'published' ? ($locked->starts_at ?? $locked->published_at) : null);
                $effectiveEndsAt = $payload['ends_at'] !== null ? Carbon::parse($payload['ends_at'])->utc() : null;
                if ($effectiveEndsAt !== null && ! $effectiveEndsAt->isAfter($effectiveStartsAt ?? now())) {
                    throw ValidationException::withMessages(['ends_at' => 'End time must be later than the start time.']);
                }

                $attributes = [
                    'institution_id' => $source->institutionId,
                    'publisher_key' => $locked?->publisher_key ?? $source->key,
                    'publisher_label' => $locked?->publisher_label ?? $source->label,
                    'program_id' => $targetProgram?->getKey(),
                    'title' => $payload['title'],
                    'summary' => $payload['summary'],
                    'body' => $payload['body'],
                    'priority' => $payload['priority'],
                    'is_featured' => $payload['is_featured'],
                    'starts_at' => $effectiveStartsAt,
                    'ends_at' => $effectiveEndsAt,
                    'revision' => $locked === null ? 1 : $locked->revision + 1,
                    'created_by' => $locked?->created_by ?? $freshActor->getKey(),
                ];

                if ($locked === null) {
                    $announcement = Announcement::query()->create($attributes);
                } else {
                    $locked->fill($attributes)->save();
                    $announcement = $locked->refresh();
                }

                $this->recordRevision($announcement, $freshActor, 'save');
                $this->audit->handle('communications.announcement_saved', [
                    'announcement_id' => $announcement->getKey(),
                    'revision' => $announcement->revision,
                    'publisher_key' => $announcement->publisher_key,
                    'status' => $announcement->status,
                ], (string) $freshActor->getKey());

                return $announcement;
            },
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
            'created_at' => now()->utc(),
        ]);
    }
}
