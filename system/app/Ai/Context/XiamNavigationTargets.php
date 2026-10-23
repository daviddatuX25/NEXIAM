<?php

declare(strict_types=1);

namespace App\Ai\Context;

use App\Admission\Support\ApplicantOfficeInquiryAccess;
use App\Applicant\Support\ApplicantNavigationBuilder;
use App\Models\User;
use App\Support\Navigation\StaffNavigationBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Current, actor-scoped navigation facts for Xiam. Providers may select a
 * reference ID; only this service can attach its current server-owned target.
 */
final class XiamNavigationTargets
{
    private const MAX_TARGETS = 12;

    public function __construct(
        private readonly ApplicantNavigationBuilder $applicantNavigation,
        private readonly StaffNavigationBuilder $staffNavigation,
    ) {}

    /**
     * @return list<array{id: string, label: string, href: string, version: 1}>
     */
    public function forActor(?User $actor, ?string $actorType = null): array
    {
        if ($actor === null) {
            return [];
        }

        $fresh = User::query()->find($actor->getKey());
        if ($fresh === null) {
            return [];
        }

        $actorType ??= $this->actorType($fresh);
        $targets = [];

        if ($actorType === 'applicant' && $fresh->applicantAccount()->exists()) {
            foreach ($this->applicantNavigation->for($fresh) as $item) {
                $targets[] = [
                    'id' => 'applicant.'.$item['key'],
                    'label' => $item['label'],
                    'href' => $this->canonicalHref($item['href']),
                    'version' => 1,
                ];
            }
            if (app(ApplicantOfficeInquiryAccess::class)->isAvailable($fresh)) {
                $targets[] = [
                    'id' => 'applicant.office_inquiries',
                    'label' => 'Office inquiries',
                    'href' => $this->canonicalHref(route('applicant.admission-support', ['group' => 'offices'])),
                    'version' => 1,
                ];
            }
        } elseif ($actorType === 'staff' && $this->activeStaffActor($fresh)) {
            foreach ($this->staffNavigation->for($fresh) as $section) {
                foreach ($section['items'] as $item) {
                    $href = $this->canonicalHref($item['href']);
                    if ($href === null) {
                        continue;
                    }
                    $routeName = $this->routeName($href);
                    if ($routeName === null) {
                        continue;
                    }
                    $targets[] = [
                        'id' => 'staff.'.$routeName,
                        'label' => $item['label'],
                        'href' => $href,
                        'version' => 1,
                    ];
                }
            }
        }

        $targets = array_values(array_filter($targets, static fn (array $target): bool => $target['href'] !== null));
        $targets = array_values(array_reduce($targets, static function (array $unique, array $target): array {
            $unique[$target['id']] ??= $target;

            return $unique;
        }, []));

        return array_slice($targets, 0, self::MAX_TARGETS);
    }

    /** @return list<string> */
    public function referencesForActor(?User $actor, ?string $actorType = null): array
    {
        return array_column($this->forActor($actor, $actorType), 'id');
    }

    /**
     * Prompt-safe navigation facts: labels and IDs only, never destinations.
     *
     * @return list<array{id: string, label: string}>
     */
    public function promptReferencesForActor(?User $actor, ?string $actorType = null): array
    {
        return array_map(
            static fn (array $target): array => ['id' => $target['id'], 'label' => $target['label']],
            $this->forActor($actor, $actorType),
        );
    }

    /**
     * Re-resolves a list of old reference IDs against current actor authority.
     * Caller-supplied labels, URLs, params, or metadata are never consulted.
     *
     * @param  list<mixed>  $references
     * @return list<array{id: string, label: string, href: string, version: 1}>
     */
    public function reproject(?User $actor, array $references, ?string $actorType = null): array
    {
        $allowed = [];
        foreach ($this->forActor($actor, $actorType) as $target) {
            $allowed[$target['id']] = $target;
        }

        $projected = [];
        foreach ($references as $reference) {
            $id = is_array($reference) ? ($reference['id'] ?? null) : $reference;
            if (! is_string($id) || ! isset($allowed[$id]) || isset($projected[$id])) {
                continue;
            }
            $projected[$id] = $allowed[$id];
        }

        return array_values($projected);
    }

    /**
     * Replaces valid provider-selected reference markers with plain labels and
     * removes all untrusted route-shaped prose. Links are returned separately.
     *
     * @return array{text: string, navigation: list<array{id: string, label: string, href: string, version: 1}>}
     */
    public function projectResponse(string $text, ?User $actor, ?string $actorType = null): array
    {
        $targets = $this->forActor($actor, $actorType);
        $byId = [];
        foreach ($targets as $target) {
            $byId[$target['id']] = $target;
        }
        $resolveTarget = static function (string $rawId) use ($byId): ?array {
            $id = trim($rawId);
            if (isset($byId[$id])) {
                return $byId[$id];
            }
            $normId = str_replace('-', '_', strtolower($id));
            foreach ($byId as $tId => $target) {
                if (str_replace('-', '_', strtolower($tId)) === $normId) {
                    return $target;
                }
            }
            $best = null;
            $bestDist = 3;
            foreach ($byId as $tId => $target) {
                $dist = levenshtein($normId, str_replace('-', '_', strtolower($tId)));
                if ($dist < $bestDist) {
                    $bestDist = $dist;
                    $best = $target;
                }
            }

            return $best;
        };

        $selected = [];
        $text = preg_replace_callback('/\[\[(?:navigate:)?([a-zA-Z0-9_.-]{1,120})\]\]/', static function (array $match) use (&$selected, $resolveTarget): string {
            $target = $resolveTarget($match[1]);
            if ($target === null) {
                return '';
            }
            $selected[$target['id']] = $target;

            return $target['label'];
        }, $text) ?? $text;
        $text = $this->removeUntrustedRouteClaims($text);

        return ['text' => trim($text), 'navigation' => array_values($selected)];
    }

    private function actorType(User $actor): ?string
    {
        if ($actor->staffProfile()->exists() && ! $actor->applicantAccount()->exists()) {
            return 'staff';
        }
        if ($actor->applicantAccount()->exists() && ! $actor->staffProfile()->exists()) {
            return 'applicant';
        }

        return null;
    }

    private function activeStaffActor(User $actor): bool
    {
        $profile = $actor->staffProfile()->first();

        return $profile !== null && $profile->isActive() && $profile->deactivated_at === null;
    }

    private function canonicalHref(string $href): ?string
    {
        $parts = parse_url($href);
        if ($parts === false || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $path = $parts['path'] ?? '';
        if (isset($parts['host'])) {
            $applicationUrl = parse_url(url('/'));
            $applicationHost = $applicationUrl['host'] ?? null;
            if (! is_string($applicationHost)
                || strtolower($parts['host']) !== strtolower($applicationHost)
                || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
                || (isset($applicationUrl['scheme']) && strtolower($parts['scheme']) !== strtolower($applicationUrl['scheme']))
                || (($parts['port'] ?? null) !== ($applicationUrl['port'] ?? null))) {
                return null;
            }
        } elseif (isset($parts['scheme'])) {
            return null;
        }
        if ($path === '' || $path[0] !== '/' || str_starts_with($path, '//') || str_contains($path, '\\')) {
            return null;
        }
        if (preg_match('/%(?:2f|5c|00)/i', $path) === 1) {
            return null;
        }
        $query = isset($parts['query']) ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return $path.$query.$fragment;
    }

    private function routeName(string $href): ?string
    {
        try {
            $route = Route::getRoutes()->match(Request::create($href, 'GET'));

            return $route->getName();
        } catch (Throwable) {
            return null;
        }
    }

    private function removeUntrustedRouteClaims(string $text): string
    {
        // These are prose claims, not citations. Never rewrite an invented path
        // into a different route the actor might be allowed to visit.
        $text = preg_replace('/(?<![A-Za-z0-9])(?:https?:\/\/[^\s<>()]+|\/\/[A-Za-z0-9.-]+[^\s<>()]*|\/(?:[A-Za-z0-9._~-]+\/?)+(?::[0-9]+)?(?:\?[^\s<>()]*)?(?:#[^\s<>()]*)?)/i', '[unverified page reference]', $text) ?? $text;

        return preg_replace('/\[\[[^\]\r\n]*\]\]/', '', $text) ?? $text;
    }
}
