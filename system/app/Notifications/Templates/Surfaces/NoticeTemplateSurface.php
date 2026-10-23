<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Surfaces;

use App\DocumentTemplate\Contracts\DocumentSurfaceInterface;
use App\DocumentTemplate\Enums\LayoutParadigm;
use App\Notifications\Templates\CommunicationTemplateRegistry;
use App\Notifications\Templates\Email\NoticeEmailSchema;
use App\ResultSheet\Builder\ResultSheetBuilderSchema;
use App\ResultSheet\Surfaces\ResultSheetSurface;
use InvalidArgumentException;
use LogicException;

final class NoticeTemplateSurface implements DocumentSurfaceInterface
{
    /** @var array<string, mixed> */
    private readonly array $slot;

    public function __construct(private readonly string $domain, private readonly string $slug)
    {
        $this->slot = CommunicationTemplateRegistry::findBySlug($domain, $slug)
            ?? throw new InvalidArgumentException('Unknown canonical notice family.');
    }

    public function surfaceKey(): string
    {
        return 'notice.'.$this->domain.'.'.$this->slug;
    }

    public function title(): string
    {
        return $this->slot['name'];
    }

    public function layoutParadigm(): LayoutParadigm
    {
        return LayoutParadigm::Flow;
    }

    /** @return array<string, mixed> */
    public function placeholderCatalog(): array
    {
        $required = match ([$this->domain, $this->slug, $this->slot['event_key']]) {
            ['admissions', 'accepted', 'admission.accepted.v1'],
            ['examination', 'session-published', 'exam.session_published.v1'] => ['application_no'],
            default => [],
        };

        return ['required' => $required, 'recommended' => [], 'all' => array_map(
            static fn (array $token): string => trim($token['token'], '{} '),
            $this->slot['tokens'],
        )];
    }

    /** @return array<string, mixed> */
    public function placeholderGroups(): array
    {
        return ['notice' => array_map(static fn (array $token): array => [
            'placeholder' => $token['token'], 'description' => $token['label'],
        ], $this->slot['tokens'])];
    }

    /** @return array<string, mixed> */
    public function sampleContext(): array
    {
        return CommunicationTemplateRegistry::sampleContextFor($this->domain, $this->slug);
    }

    public function repeatingRowKey(): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function repeatingRegions(): array
    {
        return [];
    }

    /** @return array<string, array<string, mixed>> */
    public function componentCatalog(): array
    {
        if ([$this->domain, $this->slug, $this->slot['event_key']] === ['results', 'released-online', 'result.released.online.v1']) {
            return app(ResultSheetSurface::class)->componentCatalog();
        }

        return array_intersect_key(ResultSheetBuilderSchema::componentCatalog(), array_flip([
            'header', 'field', 'note', 'footer', 'text', 'divider', 'spacer',
        ]));
    }

    /** @return array<string, mixed> */
    public function defaultBlueprint(string $paperSize = 'a4', string $orientation = 'portrait'): array
    {
        $document = ResultSheetBuilderSchema::defaultDocument($paperSize, $orientation);
        $document['layout_paradigm'] = 'flow';
        $document['components'] = [];

        return $document;
    }

    /** @return array<string, array<string, mixed>> */
    public function emailCatalog(): array
    {
        $catalog = [];
        foreach (NoticeEmailSchema::ALLOWED_BLOCK_TYPES as $type) {
            $catalog[$type] = ['label' => ucfirst($type), 'layout' => 'flow'];
        }

        return $catalog;
    }

    public function supportsPrint(): bool
    {
        return CommunicationTemplateRegistry::supportsPdf($this->domain, $this->slug, $this->slot['event_key']);
    }

    /** @return array<string, mixed> */
    public function emailDefaults(): array
    {
        return ['schema_version' => NoticeEmailSchema::SCHEMA_VERSION, 'layout' => 'flow', 'blocks' => []];
    }

    /** @return array<string, mixed> */
    public function emailCapabilities(): array
    {
        return ['layout' => 'flow', 'ordering' => true, 'spacing' => true, 'typography' => true,
            'absolute_placement' => false, 'units' => 'px', 'limits' => [
                'json_bytes' => NoticeEmailSchema::MAX_JSON_BYTES, 'blocks' => NoticeEmailSchema::MAX_BLOCKS,
                'text_characters' => NoticeEmailSchema::MAX_TEXT_CHARACTERS, 'depth' => NoticeEmailSchema::MAX_DEPTH,
            ]];
    }

    /** @return array{label: ?string, route: ?string} */
    public function actionBinding(): array
    {
        return ['label' => $this->slot['default_action_label'], 'route' => $this->slot['default_action_route']];
    }

    /** @param array<string, mixed> $filters @return array<string, mixed> */
    public function resolveData(array $filters): array
    {
        throw new LogicException('Notice recipient data must come from the authorized event producer, not an authoring surface.');
    }
}
