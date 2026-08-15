<?php

namespace App\Ai\Http;

/**
 * Versioned Inertia `nexi` prop contract (OpenSpec 1).
 *
 * @phpstan-type NexiSessionViewArray array{
 *     contract_version: string,
 *     surface: array{key: string, version: int}|null,
 *     context_epoch: string|null,
 *     page_pack: array{key: string, version: int}|null,
 *     publication_version: int|null,
 *     availability: string,
 *     modes: list<string>,
 *     active_mode: string,
 *     disclosure: array{key: string, version: int, text: string},
 *     conversation: array{id: string|null, run_id: string|null, cursor: int},
 *     usage: array{state: string, used: int|null, limit: int|null, unit: string, resets_at: string|null},
 *     proposals: list<array<string, mixed>>,
 *     manual_fallbacks: list<array{type: string, route_key?: string, label?: string}>
 * }
 */
final readonly class NexiSessionView
{
    public const CONTRACT_VERSION = '1';

    /**
     * @param  list<string>  $modes
     * @param  array{key: string, version: int, text: string}  $disclosure
     * @param  array{id: string|null, run_id: string|null, cursor: int}  $conversation
     * @param  array{state: string, used: int|null, limit: int|null, unit: string, resets_at: string|null}  $usage
     * @param  list<array<string, mixed>>  $proposals
     * @param  list<array{type: string, route_key?: string, label?: string}>  $manualFallbacks
     * @param  array{key: string, version: int}|null  $surface
     * @param  array{key: string, version: int}|null  $pagePack
     */
    public function __construct(
        public ?array $surface,
        public ?string $contextEpoch,
        public ?array $pagePack,
        public ?int $publicationVersion,
        public string $availability,
        public array $modes,
        public string $activeMode,
        public array $disclosure,
        public array $conversation,
        public array $usage,
        public array $proposals,
        public array $manualFallbacks,
        public string $contractVersion = self::CONTRACT_VERSION,
    ) {}

    /**
     * @return NexiSessionViewArray
     */
    public function toArray(): array
    {
        return [
            'contract_version' => $this->contractVersion,
            'surface' => $this->surface,
            'context_epoch' => $this->contextEpoch,
            'page_pack' => $this->pagePack,
            'publication_version' => $this->publicationVersion,
            'availability' => $this->availability,
            'modes' => $this->modes,
            'active_mode' => $this->activeMode,
            'disclosure' => $this->disclosure,
            'conversation' => $this->conversation,
            'usage' => $this->usage,
            'proposals' => $this->proposals,
            'manual_fallbacks' => $this->manualFallbacks,
        ];
    }
}
