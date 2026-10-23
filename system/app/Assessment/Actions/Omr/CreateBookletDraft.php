<?php

namespace App\Assessment\Actions\Omr;

use App\Assessment\Omr\BookletAuthorization;
use App\Assessment\Support\MaterialOwnerAccess;
use App\Authorization\CapabilityCatalog;
use App\Foundation\Actions\RecordAuditEvent;
use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrQuestionBooklet;
use App\Models\Assessment\OmrQuestionBookletVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

use function in_array;
use function strlen;
use function strtoupper;
use function substr;
use function trim;

final class CreateBookletDraft
{
    public function __construct(
        private readonly BookletAuthorization $authorization,
        private readonly MaterialOwnerAccess $materialOwnerAccess,
        private readonly RecordAuditEvent $recordAuditEvent,
    ) {}

    public function handle(
        string $name,
        string $authoringMode,
        User $actor,
        ?OmrAnswerSheetTemplateVersion $templateVersion = null,
        ?string $code = null,
        ?bool $isSynthetic = null,
    ): OmrQuestionBookletVersion {
        $this->authorization->assertManage($actor);
        $institutionId = $this->materialOwnerAccess->institutionId($actor, CapabilityCatalog::GUIDANCE_BOOKLET_MANAGE);

        $name = trim($name);
        if ($name === '') {
            throw new RuntimeException('A booklet name is required.');
        }
        if (! in_array($authoringMode, OmrQuestionBookletVersion::AUTHORING_MODES, true)) {
            throw new RuntimeException('Choose either stored questions or answer keys only.');
        }

        return DB::transaction(function () use ($name, $authoringMode, $actor, $templateVersion, $code, $isSynthetic, $institutionId): OmrQuestionBookletVersion {
            if ($isSynthetic === null) {
                $isSynthetic = $templateVersion?->template?->is_synthetic ?? false;
            }

            $bookletCode = $code === null ? $this->uniqueCode($name, $institutionId) : $this->normalizeCode($code);
            if (OmrQuestionBooklet::query()->where('institution_id', $institutionId)->where('code', $bookletCode)->exists()) {
                throw new RuntimeException('That Booklet code is already in use at this institution.');
            }
            $booklet = OmrQuestionBooklet::query()->create([
                'institution_id' => $institutionId,
                'is_synthetic' => $isSynthetic,
                'code' => $bookletCode,
                'name' => $name,
            ]);
            $version = $booklet->versions()->create([
                'template_version_id' => null,
                'test_instrument_version_id' => null,
                'version' => 1,
                'status' => OmrQuestionBookletVersion::STATUS_DRAFT,
                'authoring_mode' => $authoringMode,
                'allow_multiple_sets' => false,
                'lock_version' => 1,
                'answer_key_hash' => '',
                'content_hash' => null,
            ]);

            $this->recordAuditEvent->handle('grading.booklet_draft_created', [
                'booklet_id' => $booklet->id,
                'booklet_version_id' => $version->id,
                'authoring_mode' => $authoringMode,
                'template_version_id' => null,
            ], (string) $actor->id);

            $freshVersion = $version->fresh(['booklet', 'templateVersion']);
            if (! $freshVersion instanceof OmrQuestionBookletVersion) {
                throw new RuntimeException('The created Booklet version could not be reloaded.');
            }

            return $freshVersion;
        });
    }

    private function normalizeCode(string $value): string
    {
        $code = strtoupper(trim($value));
        if (preg_match('/^[A-Z0-9-]{3,32}$/', $code) !== 1) {
            throw new RuntimeException('Booklet codes use 3–32 uppercase letters, numbers, or hyphens.');
        }

        return $code;
    }

    private function uniqueCode(string $value, int $institutionId): string
    {
        $base = strtoupper(Str::of($value)->slug('-')->toString());
        $base = preg_replace('/[^A-Z0-9-]+/', '-', $base) ?: 'BOOKLET';
        $base = trim(substr($base, 0, 24), '-');
        $base = $base !== '' ? $base : 'BOOKLET';
        $base = substr($base, 0, 32);

        $candidate = $base;
        $counter = 1;
        while (OmrQuestionBooklet::query()->where('institution_id', $institutionId)->where('code', $candidate)->exists()) {
            $suffix = '-'.($counter++);
            $candidate = substr($base, 0, 32 - strlen($suffix)).$suffix;
        }

        return $candidate;
    }
}
