<?php

namespace App\Ai\Contracts;

/**
 * Derived knowledge index port (AD-13/AD-25). No vendor SDK types.
 */
interface KnowledgeIndexPort
{
    /**
     * @param  array{version_id: string, source_id: string, text: string, meta?: array<string, mixed>}  $document
     */
    public function upsert(array $document): void;

    public function remove(string $versionId): void;

    /**
     * @param  array{
     *     actor_id: string,
     *     actor_type: 'staff',
     *     institution_id: int,
     *     owning_office: 'registrar'|'guidance',
     *     audiences: list<string>,
     *     classifications: list<string>,
     *     purpose: string,
     *     source_classes: list<string>
     * }  $filters
     * @return list<array{
     *     version_id: string,
     *     source_id: string,
     *     institution_id: int,
     *     source_key?: string|null,
     *     source_title?: string|null,
     *     excerpt: string,
     *     score: float,
     *     audience: string,
     *     source_class: string,
     *     classification: string,
     *     owning_office: string,
     *     cross_office_approved: bool
     * }>
     */
    public function retrieve(string $query, int $limit = 5, array $filters = []): array;

    public function rebuildFromActive(): int;
}
