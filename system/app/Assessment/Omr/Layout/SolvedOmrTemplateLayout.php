<?php

namespace App\Assessment\Omr\Layout;

/**
 * Result of a successful layout solve: canonical engine template, exact
 * bytes/hash, and the page projection the frontend renders.
 */
final readonly class SolvedOmrTemplateLayout
{
    /**
     * @param  array<string, mixed>  $normalizedBlueprint
     * @param  array<string, mixed>  $template
     * @param  list<array<string, mixed>>  $pages
     * @param  array{name: string, mime: string, bytes: string, asset_hash: string}|null  $fiducial
     */
    public function __construct(
        public array $normalizedBlueprint,
        public string $blueprintHash,
        public array $template,
        public string $canonicalJson,
        public string $templateJsonHash,
        public array $pages,
        public ?array $fiducial = null,
    ) {
    }
}
