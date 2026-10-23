<?php

it('renders the comprehensive capstone vocabulary and reconciliation registry', function (): void {
    $this->get(route('capstone.glossary'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('groups', fn ($groups): bool => collect($groups)
                ->flatMap(fn (array $group): array => $group['terms'])
                ->pluck('term')
                ->intersect(['Document requirement', 'Document checklist'])
                ->values()
                ->all() === ['Document requirement', 'Document checklist'])
            ->component('Foundation/Glossary')
            ->has('groups', 6)
            ->has('sources', 57)
            ->where('groups.0.key', 'people-authority')
            ->where('groups.0.terms.0.term', 'Applicant')
            ->where('groups.0.terms.0.disposition', 'canonical')
            ->where('groups.0.terms.0.target_term', 'Applicant')
            ->where('groups.0.terms.0.reconciliation_action', fn (string $act): bool => str_contains($act, 'universal person'))
            ->where('groups.0.terms.0.surfaces.0.surface', 'Use case diagram')
            ->where('groups.0.terms.0.sources', fn ($sources): bool => collect($sources)->contains('use-case'))
            ->where('groups.5.key', 'diagrams-architecture-governance')
            ->where('stats.groups', 6)
            ->where('stats.terms', 132)
            ->where('stats.sources', 57)
            ->where('stats.dispositions.canonical', 78)
            ->where('stats.dispositions.reconcile', 20)
            ->where('stats.dispositions.to-remove', 16)
            ->where('stats.dispositions.contextual', 15)
            ->where('stats.dispositions.legacy-only', 3));
});
