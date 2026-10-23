<?php

namespace App\Assessment\Omr\Canonical;

use App\Models\Assessment\OmrAnswerSheetTemplateVersion;
use App\Models\Assessment\OmrTemplateAuthoringDraft;
use App\Models\Assessment\OmrTemplateVerificationRun;
use RuntimeException;

final class OmrSourcePolicy
{
    public const UPLOADED_ORIGINAL = 'uploaded_original';

    public const SYSTEM_DESIGNED = 'system_designed';

    public static function sourceKindForDraft(OmrTemplateAuthoringDraft $draft): ?string
    {
        $sourceKind = self::sourceKindForDraftValue((string) $draft->source_kind);
        if ($sourceKind === null || ! $draft->isCompiled()) {
            return $sourceKind;
        }

        $version = $draft->relationLoaded('compiledVersion')
            ? $draft->getRelation('compiledVersion')
            : $draft->compiledVersion()->first();
        if (! $version instanceof OmrAnswerSheetTemplateVersion) {
            return null;
        }

        $versionSourceKind = self::sourceKindForVersion($version);

        return $versionSourceKind !== null && $versionSourceKind === $sourceKind
            ? $versionSourceKind
            : null;
    }

    public static function sourceKindForVersion(OmrAnswerSheetTemplateVersion $version): ?string
    {
        $manifest = is_array($version->authoring_manifest) ? $version->authoring_manifest : [];
        $manifestDeclaresSourceKind = array_key_exists('source_kind', $manifest);
        $manifestSourceKind = self::sourceKindForVersionValue($manifest['source_kind'] ?? null);
        $compiledDrafts = $version->authoringDrafts()->get();

        if ($manifestDeclaresSourceKind) {
            if ($manifestSourceKind === null || $compiledDrafts->count() > 1) {
                return null;
            }
            if ($compiledDrafts->count() === 1) {
                $draft = $compiledDrafts->first();
                if ($draft === null
                    || $draft->stage !== OmrTemplateAuthoringDraft::STAGE_COMPILED
                    || (int) $draft->compiled_version_id !== (int) $version->id
                    || self::sourceKindForDraftValue((string) $draft->source_kind) !== $manifestSourceKind) {
                    return null;
                }
            }

            return $manifestSourceKind;
        }

        if ($compiledDrafts->count() > 1) {
            return null;
        }
        if ($compiledDrafts->count() === 1) {
            $draft = $compiledDrafts->first();
            if ($draft === null
                || $draft->stage !== OmrTemplateAuthoringDraft::STAGE_COMPILED
                || (int) $draft->compiled_version_id !== (int) $version->id) {
                return null;
            }

            return self::sourceKindForDraftValue((string) $draft->source_kind);
        }

        $artifactSet = is_array($manifest['artifact_set'] ?? null) ? $manifest['artifact_set'] : [];
        if (array_key_exists('pdf_hash', $artifactSet) && $artifactSet['pdf_hash'] === null) {
            return self::UPLOADED_ORIGINAL;
        }

        return self::SYSTEM_DESIGNED;
    }

    public static function sourceKindForRun(OmrTemplateVerificationRun $run): ?string
    {
        if (($run->draft_id === null) === ($run->template_version_id === null)) {
            return null;
        }

        if ($run->draft_id !== null) {
            $draft = $run->relationLoaded('draft')
                ? $run->getRelation('draft')
                : $run->draft()->first();

            return $draft instanceof OmrTemplateAuthoringDraft
                ? self::sourceKindForDraft($draft)
                : null;
        }

        $version = $run->relationLoaded('templateVersion')
            ? $run->getRelation('templateVersion')
            : $run->templateVersion()->first();

        return $version instanceof OmrAnswerSheetTemplateVersion
            ? self::sourceKindForVersion($version)
            : null;
    }

    public static function isSystemDesignedDraft(OmrTemplateAuthoringDraft $draft): bool
    {
        return self::sourceKindForDraft($draft) === self::SYSTEM_DESIGNED;
    }

    public static function isSystemDesignedVersion(OmrAnswerSheetTemplateVersion $version): bool
    {
        return self::sourceKindForVersion($version) === self::SYSTEM_DESIGNED;
    }

    public static function isSystemDesignedRun(OmrTemplateVerificationRun $run): bool
    {
        return self::sourceKindForRun($run) === self::SYSTEM_DESIGNED;
    }

    public static function assertSystemDesignedDraft(OmrTemplateAuthoringDraft $draft): void
    {
        self::assertSystemDesigned(self::sourceKindForDraft($draft));
    }

    public static function assertSystemDesignedVersion(OmrAnswerSheetTemplateVersion $version): void
    {
        self::assertSystemDesigned(self::sourceKindForVersion($version));
    }

    public static function assertSystemDesignedRun(OmrTemplateVerificationRun $run): void
    {
        self::assertSystemDesigned(self::sourceKindForRun($run));
    }

    private static function sourceKindForDraftValue(string $sourceKind): ?string
    {
        return match ($sourceKind) {
            OmrTemplateAuthoringDraft::SOURCE_HTML, OmrTemplateAuthoringDraft::SOURCE_PRESET => self::SYSTEM_DESIGNED,
            OmrTemplateAuthoringDraft::SOURCE_DOCX, OmrTemplateAuthoringDraft::SOURCE_PDF, OmrTemplateAuthoringDraft::SOURCE_IMAGE => self::UPLOADED_ORIGINAL,
            default => null,
        };
    }

    private static function sourceKindForVersionValue(mixed $sourceKind): ?string
    {
        return match ($sourceKind) {
            self::UPLOADED_ORIGINAL, self::SYSTEM_DESIGNED => $sourceKind,
            default => null,
        };
    }

    private static function assertSystemDesigned(?string $sourceKind): void
    {
        if ($sourceKind === self::UPLOADED_ORIGINAL) {
            throw new RuntimeException('uploaded_original_print_forbidden: Uploaded licensed sheets are private recognition references and cannot be reproduced.');
        }
        if ($sourceKind !== self::SYSTEM_DESIGNED) {
            throw new RuntimeException('source_provenance_unavailable: Printable output requires trustworthy immutable source-kind provenance.');
        }
    }
}
