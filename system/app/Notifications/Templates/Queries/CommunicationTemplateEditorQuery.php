<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Queries;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Models\User;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\CommunicationTemplateState;

final class CommunicationTemplateEditorQuery
{
    public function __construct(private readonly CommunicationTemplateScope $scope) {}

    public function template(User $actor, string $domain, string $slug, bool $fullSource = true): CommunicationTemplate
    {
        abort_if(CommunicationTemplateRegistry::findBySlug($domain, $slug) === null, 404);
        $institution = $fullSource
            ? $this->scope->authorizeFullSource($actor, $domain)
            : $this->scope->authorizeMetadata($actor, $domain);

        return CommunicationTemplate::query()->where('institution_id', $institution->id)
            ->where('domain', $domain)->where('slug', $slug)->firstOrFail();
    }

    /** @return array<string, mixed> */
    public function family(User $actor, string $domain, string $slug, mixed $version = null, mixed $inspect = null): array
    {
        $template = $this->template($actor, $domain, $slug, false);
        $template->load('draft');
        $authoringMode = CommunicationTemplateRegistry::authoringMode($domain, $slug);
        $builtin = $authoringMode === 'builtin';
        $canEdit = $this->scope->canAccessFullSource($actor, $domain);
        $versionNumber = $this->versionNumber($version);
        abort_unless($inspect === null || $inspect === 'draft', 404);
        abort_if($versionNumber !== null && $inspect !== null, 422, 'Choose a version or the retained draft.');
        $inspection = $versionNumber !== null || $inspect === 'draft';
        abort_if($inspection && (! $builtin || ! $canEdit), 403);
        $result = ['family' => $this->scope->projectMetadata($template),
            'versions' => $this->versions($template, $canEdit, $builtin),
            'draft' => $template->draft === null ? null : ['stage' => $template->draft->stage,
                'updated_at' => $template->draft->updated_at?->toIso8601String()],
            'domain' => $domain, 'authoring_mode' => $authoringMode,
            'can_edit' => $canEdit && ! $builtin && ! $inspection,
            'read_only' => $inspection,
            'back_url' => route('setup.notifications.'.$domain, [], false)];
        if (! $builtin && $canEdit && ! $inspection) {
            $result['endpoints'] = [
                'edit' => route('setup.notifications.templates.edit', ['domain' => $domain, 'slug' => $slug], false),
            ];
        }
        if ($builtin && $canEdit) {
            $result['builtin_message'] = CommunicationTemplateRegistry::builtinMessage($domain, $slug);
            if ($template->draft !== null) {
                $result['draft']['inspect_url'] = route('setup.notifications.'.$domain.'.templates.studio',
                    ['slug' => $slug, 'inspect' => 'draft'], false);
            }
        }
        if ($inspection) {
            $record = $versionNumber === null
                ? $template->draft
                : $template->versions()->where('version', $versionNumber)->firstOrFail();
            abort_if($record === null, 404);
            $state = CommunicationTemplateState::state($record);
            $title = $record->getAttribute('in_app_title') ?: $record->getAttribute('subject')
                ?: $record->getAttribute('email_subject') ?: $state['subject'] ?: '';
            $body = $record->getAttribute('in_app_body') ?: $record->getAttribute('body')
                ?: $record->getAttribute('email_body') ?: $state['body'] ?: '';
            $result['builtin_inspection'] = [
                'kind' => $versionNumber === null ? 'draft' : 'version',
                'version' => $versionNumber,
                'title' => (string) $title,
                'body' => (string) $body,
            ];
            $result['draft'] = null;
            $result['versions'] = [];
            unset($result['builtin_message'], $result['endpoints']);
        }

        return $result;
    }

    public function assertDraftScope(CommunicationTemplate $template, string $draft): void
    {
        abort_if(CommunicationTemplateDraft::query()->whereKey($draft)
            ->where('communication_template_id', '!=', $template->id)->exists(), 404);
    }

    /** @return list<array<string, mixed>> */
    private function versions(CommunicationTemplate $template, bool $fullSource, bool $builtin = false): array
    {
        return $template->versions()->orderByDesc('version')->get(['id', 'version', 'created_at'])
            ->map(function (CommunicationTemplateVersion $version) use ($template, $fullSource, $builtin): array {
                $row = ['id' => $version->id, 'version' => $version->version,
                    'created_at' => $version->created_at?->toIso8601String(),
                    'status' => $version->version === $template->active_version ? 'active' : 'historical'];
                if ($fullSource && $builtin) {
                    $row['inspect_url'] = route('setup.notifications.'.$template->domain.'.templates.studio',
                        ['slug' => $template->slug, 'version' => $version->version], false);
                }

                return $row;
            })->all();
    }

    private function versionNumber(mixed $version): ?int
    {
        if ($version === null) {
            return null;
        }
        abort_unless((is_int($version) || is_string($version)) && preg_match('/^[1-9][0-9]*$/D', (string) $version)
            && filter_var($version, FILTER_VALIDATE_INT) !== false, 404);

        return (int) $version;
    }
}
