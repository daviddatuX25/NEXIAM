<?php

namespace App\Assessment\Omr\Canonical;

use RuntimeException;

use function hash;
use function json_encode;

final class OmrOverlayException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message)
    {
        parent::__construct($errorCode.': '.$message);
    }
}

final class OmrOverlaySpec
{
    public const SCOPE_TEMPLATE = 'template';

    public const SCOPE_PRINT_JOB = 'print_job';

    public const SCOPE_BLANK_INSTANCE = 'blank_instance';

    public const ALLOWED_TAGS = [
        'institution',
        'assessment',
        'template',
        'blank_instance_code',
        'page_number',
        'page_count',
        'blank_instance_qr',
        'header_title',
        'instructions_box',
        'instruction_box',
        'signature_box',
        'signature_area',
        'instructions_area',
        'metadata_box',
        'custom_text',
        'instance_qr',
    ];

    public const SCOPES = [self::SCOPE_TEMPLATE, self::SCOPE_PRINT_JOB, self::SCOPE_BLANK_INSTANCE];

    /**
     * @param  array<string, mixed>  $overlay
     * @return array<string, mixed>
     */
    public function validate(array $overlay): array
    {
        $boxes = $overlay['boxes'] ?? [];
        if (! is_array($boxes)) {
            throw new OmrOverlayException('overlay_invalid', 'Overlay boxes must be an array.');
        }
        $normalized = [];
        foreach ($boxes as $index => $box) {
            if (! is_array($box)) {
                throw new OmrOverlayException('overlay_invalid', "Overlay box {$index} is invalid.");
            }
            $tag = (string) ($box['tag'] ?? '');
            $scope = (string) ($box['scope'] ?? self::SCOPE_TEMPLATE);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                throw new OmrOverlayException('tag_forbidden', "Overlay tag {$tag} is not permitted.");
            }
            if (! in_array($scope, self::SCOPES, true)) {
                throw new OmrOverlayException('overlay_scope_invalid', "Overlay scope {$scope} is not permitted.");
            }
            $pageNo = (int) ($box['page_no'] ?? 1);
            if ($pageNo < 1 || $pageNo > 4) {
                throw new OmrOverlayException('overlay_page_invalid', "Overlay box {$index} has an invalid page number.");
            }
            if (! isset($box['x'], $box['y'], $box['width'], $box['height'])
                || (float) $box['x'] < 0 || (float) $box['y'] < 0
                || (float) $box['width'] <= 0 || (float) $box['height'] <= 0) {
                throw new OmrOverlayException('overlay_box_invalid', "Overlay box {$index} has invalid geometry.");
            }
            $overflow = (string) ($box['overflow'] ?? 'truncate');
            if (! in_array($overflow, ['shrink', 'wrap', 'truncate'], true)) {
                throw new OmrOverlayException('overlay_overflow_invalid', "Overlay box {$index} has no fixed overflow strategy.");
            }
            $content = isset($box['content']) && $box['content'] !== null
                ? trim(strip_tags((string) $box['content']))
                : null;
            if ($content !== null && mb_strlen($content) > 500) {
                $content = mb_substr($content, 0, 500);
            }
            $name = isset($box['name']) && $box['name'] !== null
                ? trim(strip_tags((string) $box['name']))
                : null;
            if ($name !== null && mb_strlen($name) > 64) {
                $name = mb_substr($name, 0, 64);
            }
            $fontSize = isset($box['font_size']) && $box['font_size'] !== null
                ? (float) $box['font_size']
                : null;

            $normalized[] = [
                'page_no' => $pageNo,
                'tag' => $tag,
                'scope' => $scope,
                'x' => (float) $box['x'],
                'y' => (float) $box['y'],
                'width' => (float) $box['width'],
                'height' => (float) $box['height'],
                'overflow' => $overflow,
                'font_size' => $fontSize,
                'content' => $content,
                'name' => $name,
            ];
        }
        $normalized = ['boxes' => $normalized, 'version' => 1];
        $normalized['hash'] = hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        return $normalized;
    }

    /** @return list<string> */
    public function slotsFromHtml(string $html): array
    {
        preg_match_all('/data-omr-overlay\s*=\s*["\']([^"\']+)["\']/i', $html, $matches);

        return array_values(array_unique(array_map('strval', $matches[1] ?? [])));
    }
}
