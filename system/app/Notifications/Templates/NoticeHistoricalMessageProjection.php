<?php

declare(strict_types=1);

namespace App\Notifications\Templates;

use App\Models\Notifications\CommunicationTemplate;
use App\Models\Notifications\CommunicationTemplateDraft;
use App\Models\Notifications\CommunicationTemplateVersion;
use App\Notifications\Templates\Actions\StoreCommunicationTemplateDocument;
use App\Notifications\Templates\Email\NoticeEmailSchema;
use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class NoticeHistoricalMessageProjection
{
    public function __construct(private readonly StoreCommunicationTemplateDocument $documents) {}

    /**
     * @return array{
     *     state:array{subject:?string,body:?string,action_label:?string,action_route:?string,email_blueprint:?array,document_revision_id:null},
     *     conversion_review:?array{
     *         source_schema_version:string,
     *         source_renderer_version:string,
     *         status:string,
     *         email_subject:?string,
     *         email_body:?string,
     *         in_app_title:?string,
     *         in_app_body:?string,
     *         blockers:list<array{code:string,path:string,message:string}>
     *     }
     * }
     */
    public function project(CommunicationTemplateDraft|CommunicationTemplateVersion $source, CommunicationTemplate $family): array
    {
        if ((string) $source->communication_template_id !== (string) $family->id) {
            throw new RuntimeException('historical_source_mismatch');
        }
        $actionBlockers = $this->actionBlockers($source, $family);
        if ($source->content_schema_version === 'notice-content-2' && is_array($source->email_blueprint)) {
            if ($actionBlockers === []) {
                return [
                    'state' => ['subject' => $source->subject, 'body' => $source->body, 'action_label' => $source->action_label,
                        'action_route' => $source->action_route, 'email_blueprint' => $source->email_blueprint, 'document_revision_id' => null],
                    'conversion_review' => null,
                ];
            }

            return [
                'state' => ['subject' => $source->subject, 'body' => $source->body, 'action_label' => $source->action_label,
                    'action_route' => $source->action_route, 'email_blueprint' => null, 'document_revision_id' => null],
                'conversion_review' => $this->review($source, $actionBlockers),
            ];
        }

        [$blueprint, $blockers] = $this->projectEmailBody($source->email_body, $family);
        $emailTitle = trim((string) $source->email_subject);
        $portalTitle = trim((string) $source->in_app_title);
        if (($emailTitle === '') !== ($portalTitle === '')
            || ($emailTitle !== '' && $portalTitle !== '' && $emailTitle !== $portalTitle)) {
            $blockers[] = ['code' => 'differing_channel_titles', 'path' => $emailTitle === '' ? 'email_subject' : 'in_app_title',
                'message' => 'The historical Email subject and Portal title are empty or differ independently. Resolve this title mapping before conversion.'];
        }
        array_push($blockers, ...$actionBlockers);

        return [
            'state' => ['subject' => $source->in_app_title, 'body' => $source->in_app_body,
                'action_label' => $source->action_label, 'action_route' => $source->action_route,
                'email_blueprint' => $blockers === [] ? $blueprint : null, 'document_revision_id' => null],
            'conversion_review' => $this->review($source, $blockers),
        ];
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return array{
     *     source_schema_version:string,
     *     source_renderer_version:string,
     *     status:string,
     *     email_subject:?string,
     *     email_body:?string,
     *     in_app_title:?string,
     *     in_app_body:?string,
     *     blockers:list<array{code:string,path:string,message:string}>
     * }
     */
    private function review(CommunicationTemplateDraft|CommunicationTemplateVersion $source, array $blockers): array
    {
        return [
            'source_schema_version' => (string) $source->content_schema_version,
            'source_renderer_version' => (string) $source->email_renderer_version,
            'status' => $blockers === [] ? 'ready' : 'blocked',
            'email_subject' => $source->email_subject,
            'email_body' => $source->email_body,
            'in_app_title' => $source->in_app_title,
            'in_app_body' => $source->in_app_body,
            'blockers' => $blockers,
        ];
    }

    /**
     * @return list<array{code:string,path:string,message:string}>
     */
    private function actionBlockers(CommunicationTemplateDraft|CommunicationTemplateVersion $source, CommunicationTemplate $family): array
    {
        $canonicalActionRoute = CommunicationTemplateRegistry::findBySlug($family->domain, $family->slug)['default_action_route'] ?? null;
        $hasLabel = trim((string) $source->action_label) !== '';
        $hasRoute = trim((string) $source->action_route) !== '';
        if ($hasLabel === $hasRoute && (! $hasRoute || $source->action_route === $canonicalActionRoute)) {
            return [];
        }

        return [['code' => 'historical_action_not_canonical', 'path' => 'action_route',
            'message' => 'The historical action label and canonical destination are incomplete or inconsistent. Review the action before conversion.']];
    }

    /**
     * @return array{0:?array,1:list<array{code:string,path:string,message:string}>}
     */
    public function projectEmailBody(?string $emailBody, CommunicationTemplate $family): array
    {
        $blockers = [];
        if ($emailBody === null || trim($emailBody) === '') {
            return [null, [['code' => 'email_body_missing', 'path' => 'email_body',
                'message' => 'The historical email body is empty and needs explicit conversion review.']]];
        }

        $dom = new DOMDocument;
        if (! @$dom->loadHTML('<?xml encoding="utf-8" ?><div>'.$emailBody.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
            return [null, [['code' => 'html_parse_failed', 'path' => 'email_body',
                'message' => 'The historical email markup could not be parsed without losing source content.']]];
        }
        $container = $dom->documentElement;
        if (! $container instanceof DOMElement) {
            return [null, [['code' => 'html_container_missing', 'path' => 'email_body',
                'message' => 'The historical email markup has no parseable content container.']]];
        }

        $blocks = $this->blocks($container, $family, $blockers);
        if ($blocks === [] && $blockers === []) {
            $blockers[] = ['code' => 'email_body_unmapped', 'path' => 'email_body',
                'message' => 'The historical email body has no supported semantic content.'];
        }
        $blueprint = ['schema_version' => NoticeEmailSchema::SCHEMA_VERSION,
            'layout' => NoticeEmailSchema::LAYOUT, 'blocks' => $blocks];
        foreach (NoticeEmailSchema::diagnostics($blueprint, CommunicationTemplateState::tokens($family)) as $diagnostic) {
            $blockers[] = ['code' => (string) ($diagnostic['code'] ?? 'email_schema_invalid'),
                'path' => (string) ($diagnostic['path'] ?? 'email_body'),
                'message' => (string) ($diagnostic['message'] ?? 'The historical email needs explicit conversion review.')];
        }

        return [$blueprint, $blockers];
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return list<array<string,mixed>>
     */
    private function blocks(DOMNode $parent, CommunicationTemplate $family, array &$blockers): array
    {
        $blocks = [];
        foreach ($parent->childNodes as $child) {
            array_push($blocks, ...$this->projectNode($child, $family, $blockers));
        }

        return $blocks;
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return list<array<string,mixed>>
     */
    private function projectNode(DOMNode $child, CommunicationTemplate $family, array &$blockers): array
    {
        if ($child->nodeType === XML_TEXT_NODE) {
            return trim($child->textContent) !== '' ? [$this->paragraph($child, $blockers)] : [];
        }
        if (! $child instanceof DOMElement) {
            return [];
        }

        $tag = strtolower($child->tagName);
        $allowedAttributes = match ($tag) {
            'a' => ['href'],
            'span' => ['style'],
            default => [],
        };
        if ($tag !== 'img') {
            $this->unmappedAttributes($child, $allowedAttributes, $blockers);
        }
        if (in_array($tag, ['html', 'body', 'div', 'section', 'article'], true)) {
            return $this->blocks($child, $family, $blockers);
        }
        if ($tag === 'p') {
            return $this->paragraphContent($child, $family, $blockers);
        }
        if (preg_match('/^h([1-6])$/', $tag, $match) === 1) {
            return [['type' => 'heading', 'level' => (int) $match[1], ...$this->runs($child, [], $blockers)]];
        }
        if ($tag === 'ul' || $tag === 'ol') {
            $items = [];
            foreach ($child->childNodes as $item) {
                if (! $item instanceof DOMElement || strtolower($item->tagName) !== 'li') {
                    if ($item->nodeType !== XML_TEXT_NODE || trim($item->textContent) !== '') {
                        $blockers[] = ['code' => 'list_content_unmapped', 'path' => 'email_body',
                            'message' => 'List content outside an item cannot be mapped without review.'];
                    }

                    continue;
                }
                $items[] = $this->listItem($item, $family, $blockers);
            }

            return $items === [] ? [] : [['type' => 'list', 'ordered' => $tag === 'ol', 'items' => $items]];
        }
        if ($tag === 'br') {
            return [['type' => 'paragraph', 'text' => "\n"]];
        }
        if ($tag === 'img') {
            $image = $this->image($child, $family, $blockers);

            return $image === null ? [] : [$image];
        }

        $blockers[] = ['code' => 'unsupported_html_element', 'path' => 'email_body',
            'message' => 'Historical element <'.$tag.'> has no lossless message mapping.'];

        return $child->hasChildNodes() ? $this->blocks($child, $family, $blockers) : [];
    }

    /** @param list<array{code:string,path:string,message:string}> $blockers */
    private function paragraph(DOMNode $node, array &$blockers): array
    {
        return ['type' => 'paragraph', ...$this->runs($node, [], $blockers)];
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return list<array<string,mixed>>
     */
    private function paragraphContent(DOMElement $paragraph, CommunicationTemplate $family, array &$blockers): array
    {
        $blocks = [];
        $runs = [];
        foreach ($paragraph->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->tagName) === 'img') {
                if ($runs !== []) {
                    $blocks[] = $this->paragraphFromRuns($runs);
                    $runs = [];
                }
                $image = $this->image($child, $family, $blockers);
                if ($image !== null) {
                    $blocks[] = $image;
                }

                continue;
            }
            array_push($runs, ...$this->textRuns($child, [], $blockers));
        }
        if ($runs !== []) {
            $blocks[] = $this->paragraphFromRuns($runs);
        }

        return $blocks === [] ? [$this->paragraph($paragraph, $blockers)] : $blocks;
    }

    private function paragraphFromRuns(array $runs): array
    {
        return count($runs) === 1 && array_keys($runs[0]) === ['text']
            ? ['type' => 'paragraph', 'text' => $runs[0]['text']]
            : ['type' => 'paragraph', 'runs' => $runs];
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return array{runs:list<array<string,mixed>>}|array{text:string}
     */
    private function runs(DOMNode $node, array $format, array &$blockers): array
    {
        $runs = $this->textRuns($node, $format, $blockers);
        if (count($runs) === 1 && array_keys($runs[0]) === ['text']) {
            return ['text' => $runs[0]['text']];
        }

        return ['runs' => $runs];
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return list<array<string,mixed>>
     */
    private function textRuns(DOMNode $node, array $format, array &$blockers): array
    {
        if ($node->nodeType === XML_TEXT_NODE) {
            return [['text' => $node->textContent, ...$format]];
        }
        if (! $node instanceof DOMElement) {
            $runs = [];
            foreach ($node->childNodes as $child) {
                array_push($runs, ...$this->textRuns($child, $format, $blockers));
            }

            return $runs;
        }

        $tag = strtolower($node->tagName);
        if (in_array($tag, ['strong', 'b', 'em', 'i', 'u', 'span', 'a', 'br'], true)) {
            $allowedAttributes = match ($tag) {
                'span' => ['style'],
                'a' => ['href'],
                default => [],
            };
            $this->unmappedAttributes($node, $allowedAttributes, $blockers);
        }
        if ($tag === 'img') {
            $blockers[] = ['code' => 'inline_image_unmapped', 'path' => 'email_body',
                'message' => 'An inline historical image cannot be represented as a standalone semantic image block.'];

            return [];
        }
        $style = trim($node->getAttribute('style'));
        $hasSupportedColor = $tag === 'span' && preg_match('/^\s*color\s*:\s*#[0-9a-f]{3}(?:[0-9a-f]{3})?\s*;?\s*$/i', $style) === 1;
        if ($style !== '' && ! $hasSupportedColor) {
            $blockers[] = ['code' => 'inline_style_unmapped', 'path' => 'email_body',
                'message' => 'Historical inline styling has no lossless semantic mapping.'];
        }
        if (! in_array($tag, ['p', 'br', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'strong', 'b', 'em', 'i', 'u', 'span', 'a'], true)) {
            $blockers[] = ['code' => 'unsupported_inline_element', 'path' => 'email_body',
                'message' => 'Historical inline element <'.$tag.'> has no lossless semantic mapping.'];
        }
        if ($tag === 'br') {
            return [['text' => "\n", ...$format]];
        }
        if (in_array($tag, ['strong', 'b'], true)) {
            $format['bold'] = true;
        } elseif (in_array($tag, ['em', 'i'], true)) {
            $format['italic'] = true;
        } elseif ($tag === 'u') {
            $format['underline'] = true;
        } elseif ($hasSupportedColor) {
            preg_match('/#([0-9a-f]{3}(?:[0-9a-f]{3})?)/i', $style, $match);
            $format['color'] = '#'.$match[1];
        } elseif ($tag === 'a') {
            $href = trim($node->getAttribute('href'));
            if ($href === '') {
                $blockers[] = ['code' => 'link_destination_missing', 'path' => 'email_body',
                    'message' => 'A historical email link has no destination.'];
            } else {
                $format['link'] = $href;
            }
        }

        $runs = [];
        foreach ($node->childNodes as $child) {
            array_push($runs, ...$this->textRuns($child, $format, $blockers));
        }

        return $runs;
    }

    /**
     * @param  list<string>  $allowed
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     */
    private function unmappedAttributes(DOMElement $node, array $allowed, array &$blockers): void
    {
        foreach ($node->attributes as $attribute) {
            if (! in_array(strtolower($attribute->name), $allowed, true)) {
                $blockers[] = ['code' => 'html_attribute_unmapped', 'path' => 'email_body',
                    'message' => 'Historical HTML attribute '.$attribute->name.' has no lossless semantic mapping.'];
            }
        }
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return array<string,mixed>
     */
    private function listItem(DOMElement $item, CommunicationTemplate $family, array &$blockers): array
    {
        $this->unmappedAttributes($item, [], $blockers);
        $runs = [];
        $blocks = [];
        $hasNested = false;
        foreach ($item->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->tagName), ['ul', 'ol'], true)) {
                if ($runs !== []) {
                    $blocks[] = $this->paragraphFromRuns($runs);
                    $runs = [];
                }
                array_push($blocks, ...$this->projectNode($child, $family, $blockers));
                $hasNested = true;

                continue;
            }
            array_push($runs, ...$this->textRuns($child, [], $blockers));
        }
        if ($runs !== []) {
            $blocks[] = $this->paragraphFromRuns($runs);
        }
        if ($hasNested) {
            return ['blocks' => $blocks];
        }

        return count($runs) === 1 && array_keys($runs[0]) === ['text']
            ? ['text' => $runs[0]['text']] : ['runs' => $runs];
    }

    /**
     * @param  list<array{code:string,path:string,message:string}>  $blockers
     * @return array<string,mixed>|null
     */
    private function image(DOMElement $image, CommunicationTemplate $family, array &$blockers): ?array
    {
        $source = trim($image->getAttribute('data-asset-id') ?: $image->getAttribute('src'));
        $this->unmappedAttributes($image, ['src', 'data-asset-id', 'alt', 'width', 'height'], $blockers);
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $source) !== 1) {
            $blockers[] = ['code' => 'image_reference_not_owned', 'path' => 'email_body',
                'message' => 'A historical image is not identified by an owned private asset reference.'];

            return null;
        }
        try {
            $asset = $this->documents->verified($family, $source);
            if (! in_array($asset->mime_type, ['image/png', 'image/jpeg'], true)) {
                throw new RuntimeException('unsupported_mime');
            }
            $bytes = Storage::disk('local')->get($asset->storage_path);
            $imageInfo = is_string($bytes) ? getimagesizefromstring($bytes) : false;
            if (! is_array($imageInfo)) {
                throw new RuntimeException('invalid_image');
            }
        } catch (\Throwable) {
            $blockers[] = ['code' => 'image_reference_unavailable', 'path' => 'email_body',
                'message' => 'A historical image does not resolve to a verified private image owned by this notice.'];

            return null;
        }

        $width = min(600, (int) $imageInfo[0]);
        $sourceWidth = trim($image->getAttribute('width'));
        if ($sourceWidth === '' && (int) $imageInfo[0] > 600) {
            $blockers[] = ['code' => 'image_intrinsic_width_exceeds_bound', 'path' => 'email_body',
                'message' => 'The historical image is wider than the supported semantic image bound.'];
        }
        if ($sourceWidth !== '') {
            if (preg_match('/^(100|[1-9][0-9]?)%$/', $sourceWidth) === 1) {
                $width = $sourceWidth;
            } elseif (preg_match('/^[1-9][0-9]*$/', $sourceWidth) === 1 && (int) $sourceWidth <= 600) {
                $width = (int) $sourceWidth;
            } else {
                $blockers[] = ['code' => 'image_width_unmapped', 'path' => 'email_body',
                    'message' => 'The historical image width has no supported semantic mapping.'];
            }
        }
        $height = (int) $imageInfo[1];
        $sourceHeight = trim($image->getAttribute('height'));
        if ($sourceHeight !== '') {
            if (preg_match('/^[1-9][0-9]*$/', $sourceHeight) === 1 && (int) $sourceHeight <= 20000000) {
                $height = (int) $sourceHeight;
                $blockers[] = ['code' => 'image_explicit_height_unrepresentable', 'path' => 'email_body',
                    'message' => 'The historical image has an explicit rendered height that cannot be represented safely.'];
            } else {
                $blockers[] = ['code' => 'image_height_unmapped', 'path' => 'email_body',
                    'message' => 'The historical image height has no supported semantic mapping.'];
            }
        }

        return ['type' => 'image', 'asset_id' => (string) $asset->id,
            'alt' => $image->getAttribute('alt'), 'width' => $width, 'height' => $height];
    }
}
