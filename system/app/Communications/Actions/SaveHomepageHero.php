<?php

declare(strict_types=1);

namespace App\Communications\Actions;

use App\Communications\HomepageAuthorizer;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Communications\PublicHomepageMedia;
use App\Models\Communications\PublicHomepageSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

final class SaveHomepageHero
{
    public function __construct(
        private readonly HomepageAuthorizer $authorizer,
        private readonly RecordAuditEvent $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function handle(User $actor, array $data): PublicHomepageSection
    {
        $validated = Validator::make($data, [
            'eyebrow' => ['nullable', 'string', 'max:255'],
            'title' => ['required', 'string', 'max:255'],
            'lede' => ['required', 'string', 'max:2000'],
            'badge_text' => ['nullable', 'string', 'max:120'],
            'image_id' => ['nullable', 'integer', 'min:1'],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'expected_version' => ['required', 'integer', 'min:1'],
        ])->validate();

        $institutionId = $this->authorizer->assertCanManage($actor);

        return DB::transaction(function () use ($actor, $validated, $institutionId): PublicHomepageSection {
            $section = PublicHomepageSection::query()->where('institution_id', $institutionId)
                ->where('key', 'hero')->lockForUpdate()->first();
            if ($section === null) {
                $section = PublicHomepageSection::query()->create([
                    'institution_id' => $institutionId,
                    'key' => 'hero',
                    'title' => 'Welcome',
                    'version' => 1,
                    'is_active' => true,
                ]);
            }
            if ((int) $section->version !== (int) $validated['expected_version']) {
                throw ValidationException::withMessages(['version' => 'The homepage hero changed. Reload and try again.']);
            }
            $imageId = $validated['image_id'] ?? null;
            if ($imageId !== null && ! PublicHomepageMedia::query()->whereKey($imageId)
                ->where('institution_id', $institutionId)->exists()) {
                throw ValidationException::withMessages(['image_id' => 'Choose an image staged for this institution.']);
            }
            $section->fill([
                'eyebrow' => $validated['eyebrow'] ?? null,
                'title' => trim($validated['title']),
                'body' => trim($validated['lede']),
                'badge_text' => $validated['badge_text'] ?? null,
                'image_id' => $imageId,
                'image_alt' => $validated['image_alt'] ?? null,
                'image_source' => $imageId === null ? $section->image_source : null,
                'updated_by' => $actor->id,
                'version' => $section->version + 1,
            ])->save();
            $this->audit->handle('communications.homepage_hero_saved', [
                'institution_id' => $institutionId, 'section_id' => $section->id, 'version' => $section->version,
            ], (string) $actor->id);

            return $section;
        });
    }
}
