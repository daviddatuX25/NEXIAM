<?php

namespace App\Ai\Gateway;

use App\Application\Capabilities\CapabilityDefinition;
use App\Application\Capabilities\CapabilityKind;
use InvalidArgumentException;
use LogicException;

/**
 * Self-contained AI tool definition for AssistantToolCatalog (D-XIAM-01).
 *
 * @phpstan-type XiamExposure array{human_path: non-empty-string, privacy_projection: non-empty-string, confirmation: non-empty-string, idempotency: non-empty-string, owner: non-empty-string}
 */
final readonly class AssistantToolDefinition
{
    public string $key;

    public int $version;

    public CapabilityKind $kind;

    /** @var list<string> */
    public array $applicationCapabilities;

    public string $authorizationMode;

    /** @var array<string, mixed> */
    public array $inputSchema;

    public string $handlerClass;

    public string $description;

    public ?string $publicationBinding;

    /** @var array<string, mixed>|null */
    public ?array $xiamExposure;

    public CapabilityDefinition $capability;

    public ?string $governance_scope;

    public ?string $display_group;

    public ?string $display_title;

    public ?string $governanceScope;

    public ?string $displayGroup;

    public ?string $displayTitle;

    /**
     * @param  list<string>  $applicationCapabilities
     * @param  array<string, mixed>  $inputSchema
     * @param  array<string, mixed>|null  $xiamExposure
     */
    public function __construct(
        string|CapabilityDefinition $keyOrCapability,
        ?int $version = null,
        ?CapabilityKind $kind = null,
        array $applicationCapabilities = [],
        string $authorizationMode = 'any',
        array $inputSchema = [],
        string $handlerClass = '',
        string $description = '',
        ?array $xiamExposure = null,
        ?string $publicationBinding = null,
        ?string $governanceScope = null,
        ?string $displayGroup = null,
        ?string $displayTitle = null,
    ) {
        if ($keyOrCapability instanceof CapabilityDefinition) {
            $cap = $keyOrCapability;
            if (! in_array($cap->kind, [CapabilityKind::Read, CapabilityKind::Propose], true)) {
                throw new LogicException('Only read/propose capabilities may enter the assistant catalog.');
            }
            $this->capability = $cap;
            $this->key = $cap->key;
            $this->version = $cap->version;
            $this->kind = $cap->kind;
            $this->applicationCapabilities = $cap->applicationCapabilities;
            $this->authorizationMode = $cap->authorizationMode;
            $this->inputSchema = $cap->inputSchema;
            $this->handlerClass = $cap->handlerClass;
            $this->description = $cap->description;
            $this->xiamExposure = $cap->xiamExposure;
            $this->publicationBinding = $cap->publicationBinding ?? ($cap->key.'@'.$cap->version);
        } else {
            if ($keyOrCapability === '' || ($version !== null && $version < 1)) {
                throw new InvalidArgumentException('Tool key/version invalid.');
            }
            $resolvedKind = $kind ?? CapabilityKind::Read;
            if (! in_array($resolvedKind, [CapabilityKind::Read, CapabilityKind::Propose], true)) {
                throw new LogicException('Only read/propose capabilities may enter the assistant catalog.');
            }
            $this->key = $keyOrCapability;
            $this->version = $version ?? 1;
            $this->kind = $resolvedKind;
            $this->applicationCapabilities = $applicationCapabilities;
            $this->authorizationMode = in_array($authorizationMode, ['any', 'all'], true) ? $authorizationMode : 'any';
            $this->inputSchema = $inputSchema;
            $this->handlerClass = $handlerClass;
            $this->description = $description;
            $this->xiamExposure = $xiamExposure;
            $this->publicationBinding = $publicationBinding ?? ($this->key.'@'.$this->version);

            $this->capability = new CapabilityDefinition(
                key: $this->key,
                version: $this->version,
                kind: $this->kind,
                applicationCapabilities: $this->applicationCapabilities !== [] ? $this->applicationCapabilities : ['xiam.system'],
                authorizationMode: $this->authorizationMode,
                inputSchema: $this->inputSchema,
                handlerClass: $this->handlerClass,
                channels: ['xiam_safe'],
                ruleSetRefs: [$this->publicationBinding],
                publicationBinding: $this->publicationBinding,
                description: $this->description,
                xiamExposure: $this->xiamExposure ?? [
                    'human_path' => 'xiam',
                    'privacy_projection' => class_basename($this->handlerClass).':xiam_safe',
                    'confirmation' => $this->kind === CapabilityKind::Read ? 'none' : 'explicit_proposal',
                    'idempotency' => $this->kind === CapabilityKind::Read ? 'read_only' : 'proposal_execution_receipt',
                    'owner' => 'system',
                ],
                governanceScope: $governanceScope,
                displayGroup: $displayGroup,
                displayTitle: $displayTitle,
            );
        }

        $this->governance_scope = $this->capability->governance_scope;
        $this->display_group = $this->capability->display_group;
        $this->display_title = $this->capability->display_title;
        $this->governanceScope = $this->governance_scope;
        $this->displayGroup = $this->display_group;
        $this->displayTitle = $this->display_title;
    }

    public function id(): string
    {
        return $this->key.'@'.$this->version;
    }

    public function isProposal(): bool
    {
        return $this->kind === CapabilityKind::Propose;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id(),
            'key' => $this->key,
            'version' => $this->version,
            'kind' => $this->kind->value,
            'input_schema' => $this->inputSchema,
            'description' => $this->description,
            'governance_scope' => $this->governance_scope,
            'display_group' => $this->display_group,
            'display_title' => $this->display_title,
            'publication_binding' => $this->publicationBinding,
        ];
    }
}
