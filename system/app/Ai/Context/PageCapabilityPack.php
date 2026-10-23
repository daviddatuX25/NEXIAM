<?php

namespace App\Ai\Context;

use App\Ai\Surface\SurfaceProfileKey;

/**
 * Route-scoped relevance pack. Never grants authorization.
 *
 * @phpstan-type PagePackArray array{
 *     key: string,
 *     version: int,
 *     page_identity: string,
 *     privacy_class: string,
 *     surface: string,
 *     capabilities: list<string>,
 *     skills: list<string>,
 *     ui_intents: list<array{type: string, route_key?: string, label?: string}>
 * }
 */
final readonly class PageCapabilityPack
{
    /**
     * @param  list<string>  $capabilities
     * @param  list<string>  $skills
     * @param  list<array{type: string, route_key?: string, label?: string}>  $uiIntents
     */
    public function __construct(
        public string $key,
        public int $version,
        public string $pageIdentity,
        public string $privacyClass,
        public SurfaceProfileKey $surface,
        public array $capabilities = [],
        public array $skills = [],
        public array $uiIntents = [],
    ) {}

    /**
     * @return PagePackArray
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'version' => $this->version,
            'page_identity' => $this->pageIdentity,
            'privacy_class' => $this->privacyClass,
            'surface' => $this->surface->value,
            'capabilities' => $this->capabilities,
            'skills' => $this->skills,
            'ui_intents' => $this->uiIntents,
        ];
    }

    /**
     * @return array{key: string, version: int}
     */
    public function identity(): array
    {
        return [
            'key' => $this->key,
            'version' => $this->version,
        ];
    }
}
