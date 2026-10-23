<?php

namespace App\Ai\Http;

/**
 * Versioned Inertia `xiam` prop contract (OpenSpec 1).
 *
 * @phpstan-type XiamSessionViewArray array{
 *     contract_version: string,
 *     surface: array{key: string, version: int}|null,
 *     context_epoch: string|null,
 *     page_pack: array{key: string, version: int}|null,
 *     publication_version: int|null,
 *     availability: string,
 *     disclosure: array{key: string, version: int, text: string},
 *     conversation: array{id: string|null, run_id: string|null, cursor: int},
 *     usage: array{state: string, used: int|null, limit: int|null, remaining: int|null, credit_balance: int, unit: string, period: string, resets_at: string|null},
 *     active_mode: string,
 *     interaction_modes: list<array{key: string, label: string}>,
 *     proposals: list<array<string, mixed>>,
 *     manual_fallbacks: list<array{type: string, route_key?: string, label?: string}>
 * }
 */
final readonly class XiamSessionView
{
    public const CONTRACT_VERSION = '2';

    /**
     * @param  array{key: string, version: int, text: string}  $disclosure
     * @param  array{id: string|null, run_id: string|null, cursor: int}  $conversation
     * @param  array{state: string, used: int|null, limit: int|null, remaining: int|null, credit_balance: int, unit: string, period: string, resets_at: string|null}  $usage
     * @param  list<array{key: string, label: string}>  $interactionModes
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
        public array $disclosure,
        public array $conversation,
        public array $usage,
        public array $proposals,
        public array $manualFallbacks,
        public string $activeMode,
        public array $interactionModes,
        public string $contractVersion = self::CONTRACT_VERSION,
    ) {}

    /**
     * @return XiamSessionViewArray
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
            'disclosure' => $this->disclosure,
            'conversation' => $this->conversation,
            'usage' => $this->usage,
            'active_mode' => $this->activeMode,
            'interaction_modes' => $this->interactionModes,
            'proposals' => $this->proposals,
            'manual_fallbacks' => $this->manualFallbacks,
        ];
    }
}
