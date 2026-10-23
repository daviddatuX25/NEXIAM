<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Queries;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\User;
use App\Notifications\Support\CommunicationTemplateScope;
use App\Notifications\Templates\CommunicationTemplateRegistry;

final class NoticeTemplateNavigationQuery
{
    /** @var list<array{key: string, label: string, href: string}> */
    private const DESKS = [
        ['key' => 'admissions', 'label' => 'Admissions Desk', 'href' => '/setup/notifications/admissions'],
        ['key' => 'examination', 'label' => 'Examinations Desk', 'href' => '/setup/notifications/examination'],
        ['key' => 'results', 'label' => 'Results & Counseling Desk', 'href' => '/setup/notifications/results'],
    ];

    public function __construct(private readonly CommunicationTemplateScope $scope) {}

    /** @return array{current_domain: string, current_slug: null, desks: list<array{key: string, label: string, href: string, can_access: bool}>, siblings: list<array{id: string, slug: string, name: string, active_version: int, has_draft: bool, authoring_mode: string, url: string}>} */
    public function forDesk(User $user, string $domain): array
    {
        $institution = $this->scope->authorizeMetadata($user, $domain);

        return $this->project($user, $domain, null, (int) $institution->id);
    }

    /** @return array{current_domain: string, current_slug: string, desks: list<array{key: string, label: string, href: string, can_access: bool}>, siblings: list<array{id: string, slug: string, name: string, active_version: int, has_draft: bool, authoring_mode: string, url: string}>} */
    public function forComposer(User $user, string $domain, string $slug): array
    {
        $institution = $this->scope->authorizeMetadata($user, $domain);
        abort_unless(CommunicationTemplateRegistry::findBySlug($domain, $slug) !== null, 404);

        return $this->project($user, $domain, $slug, (int) $institution->id);
    }

    /** @return array{current_domain: string, current_slug: string|null, desks: list<array{key: string, label: string, href: string, can_access: bool}>, siblings: list<array{id: string, slug: string, name: string, active_version: int, has_draft: bool, authoring_mode: string, url: string}>} */
    private function project(User $user, string $domain, ?string $slug, int $institutionId): array
    {
        $desks = [];
        foreach (self::DESKS as $desk) {
            if ($this->scope->canAccessMetadata($user, $desk['key'])) {
                $desks[] = [...$desk, 'can_access' => true];
            }
        }

        $canEdit = $this->scope->canAccessFullSource($user, $domain);
        $siblings = CommunicationTemplate::query()
            ->where('institution_id', $institutionId)
            ->where('domain', $domain)
            ->with('draft:id,communication_template_id')
            ->orderBy('name')
            ->get(['id', 'domain', 'slug', 'active_version'])
            ->map(function (CommunicationTemplate $family) use ($domain, $canEdit): ?array {
                $slot = CommunicationTemplateRegistry::findBySlug($domain, $family->slug);
                if ($slot === null) {
                    return null;
                }

                $authoringMode = CommunicationTemplateRegistry::authoringMode($domain, $family->slug);

                return [
                    'id' => (string) $family->id,
                    'slug' => (string) $family->slug,
                    'name' => (string) $slot['name'],
                    'active_version' => (int) $family->active_version,
                    'has_draft' => $family->draft !== null,
                    'authoring_mode' => $authoringMode,
                    'url' => $canEdit && $authoringMode !== 'builtin'
                        ? "/setup/notifications/{$domain}/templates/{$family->slug}/edit"
                        : "/setup/notifications/{$domain}/templates/{$family->slug}",
                ];
            })
            ->filter()
            ->values()
            ->all();

        return [
            'current_domain' => $domain,
            'current_slug' => $slug,
            'desks' => $desks,
            'siblings' => $siblings,
        ];
    }
}
