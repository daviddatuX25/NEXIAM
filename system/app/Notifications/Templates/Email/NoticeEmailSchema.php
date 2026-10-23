<?php

declare(strict_types=1);

namespace App\Notifications\Templates\Email;

use App\Notifications\Templates\CommunicationTemplatePayload;
use App\Notifications\Templates\CommunicationTemplateRegistry;

final class NoticeEmailSchema
{
    public const SCHEMA_VERSION = 'notice-email-1';

    public const LAYOUT = 'flow';

    public const MAX_JSON_BYTES = 262144;

    public const MAX_BLOCKS = 200;

    public const MAX_TEXT_CHARACTERS = 20000;

    public const MAX_DEPTH = 4;

    public const ALLOWED_BLOCK_TYPES = ['paragraph', 'heading', 'list', 'table', 'image', 'divider', 'spacer'];

    public const ALLOWED_RUN_KEYS = ['text', 'bold', 'italic', 'underline', 'color', 'link', 'href', 'token'];

    public const ALLOWED_ALIGNMENTS = ['left', 'center', 'right', 'justify'];

    public const STYLE_KEYS = ['font_family', 'font_size', 'color', 'background_color', 'align', 'spacing_before', 'spacing_after', 'padding', 'width'];

    public const FONTS = ['Arial', 'Verdana', 'Georgia', 'Times New Roman', 'Tahoma', 'Trebuchet MS'];

    public static function assertValid(array $blueprint, ?array $allowedTokens = null): void
    {
        $diagnostics = self::diagnostics($blueprint, $allowedTokens);
        if ($diagnostics !== []) {
            throw new NoticeEmailSchemaValidationException('Notice email blueprint validation failed.', $diagnostics);
        }
    }

    public static function validate(array $blueprint, ?array $allowedTokens = null): array
    {
        self::assertValid($blueprint, $allowedTokens);

        return $blueprint;
    }

    public static function isValid(array $blueprint, ?array $allowedTokens = null): bool
    {
        return self::diagnostics($blueprint, $allowedTokens) === [];
    }

    public static function diagnostics(array $blueprint, ?array $allowedTokens = null): array
    {
        $validator = new self;
        $validator->tokens = $allowedTokens ?? self::catalogTokens();
        try {
            if (strlen(CommunicationTemplatePayload::json($blueprint)) > self::MAX_JSON_BYTES) {
                $validator->error('max_json_bytes_exceeded', '$', 'Maximum canonical JSON size is 256 KiB.');
            }
        } catch (\JsonException) {
            $validator->error('invalid_json', '$', 'The email blueprint must serialize as valid JSON.');

            return $validator->errors;
        }
        $validator->keys($blueprint, ['schema_version', 'layout', 'blocks'], '$');
        if (($blueprint['schema_version'] ?? null) !== self::SCHEMA_VERSION) {
            $validator->error('invalid_schema_version', '$.schema_version', 'Expected notice-email-1.');
        }
        if (($blueprint['layout'] ?? null) !== self::LAYOUT) {
            $validator->error('invalid_layout', '$.layout', 'Email layout must be flow.');
        }
        if (! is_array($blueprint['blocks'] ?? null) || ! array_is_list($blueprint['blocks'])) {
            $validator->error('blocks_not_array', '$.blocks', 'Blocks must be an ordered list.');
        } else {
            foreach ($blueprint['blocks'] as $index => $block) {
                $validator->block($block, '$.blocks['.$index.']', 1);
            }
        }
        if ($validator->blocks > self::MAX_BLOCKS) {
            $validator->error('max_blocks_exceeded', '$.blocks', 'At most 200 blocks, list items, table rows and cells are allowed.');
        }
        if ($validator->characters > self::MAX_TEXT_CHARACTERS) {
            $validator->error('max_text_characters_exceeded', '$.blocks', 'At most 20,000 authored text characters are allowed.');
        }
        if ($validator->images > 20) {
            $validator->error('max_images_exceeded', '$.blocks', 'At most 20 raster images are allowed.');
        }

        return $validator->errors;
    }

    private array $errors = [];

    private array $tokens = [];

    private int $blocks = 0;

    private int $characters = 0;

    private int $images = 0;

    private function block(mixed $block, string $path, int $depth): void
    {
        $this->blocks++;
        if ($depth > self::MAX_DEPTH) {
            $this->error('max_depth_exceeded', $path, 'Semantic nesting is limited to depth four.');

            return;
        }
        if (! is_array($block) || array_is_list($block)) {
            $this->error('block_not_object', $path, 'A block must be an object.');

            return;
        }
        $type = $block['type'] ?? null;
        $typeKeys = match ($type) {
            'paragraph' => ['text', 'runs'], 'heading' => ['text', 'runs', 'level'], 'list' => ['ordered', 'items'],
            'table' => ['headers', 'rows'], 'image' => ['asset_id', 'alt', 'width', 'height'],
            'divider' => ['color'], 'spacer' => ['height'], default => null,
        };
        if ($typeKeys === null) {
            $this->error('unsupported_block_type', $path, 'Unsupported email block.');

            return;
        }
        $this->keys($block, ['type', 'align', 'style', ...$typeKeys], $path);
        if (array_key_exists('align', $block)) {
            $this->alignment($block['align'], $path.'.align');
        }
        if (array_key_exists('style', $block)) {
            $this->style($block['style'], $path.'.style');
        }
        if ($type === 'paragraph' || $type === 'heading') {
            if ($type === 'heading' && (! is_int($block['level'] ?? null) || $block['level'] < 1 || $block['level'] > 6)) {
                $this->error('invalid_heading_level', $path.'.level', 'Heading level must be 1 through 6.');
            }
            $this->content($block, $path);
        } elseif ($type === 'list') {
            if (array_key_exists('ordered', $block) && ! is_bool($block['ordered'])) {
                $this->error('invalid_list_order', $path.'.ordered', 'Ordered must be boolean.');
            }
            if (! is_array($block['items'] ?? null) || ! array_is_list($block['items']) || $block['items'] === []) {
                $this->error('list_items_not_array', $path.'.items', 'List items must be a nonempty ordered list.');
            } else {
                foreach ($block['items'] as $index => $item) {
                    $this->cell($item, $path.'.items['.$index.']', $depth + 1, false);
                }
            }
        } elseif ($type === 'table') {
            $this->table($block, $path, $depth);
        } elseif ($type === 'image') {
            $this->images++;
            if (! is_string($block['asset_id'] ?? null) || ! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $block['asset_id'])) {
                $this->error('image_asset_id_missing', $path.'.asset_id', 'Use an opaque owned image UUID.');
                if (is_string($block['asset_id'] ?? null) && preg_match('/[:\/]/', $block['asset_id'])) {
                    $this->error('image_external_url_rejected', $path.'.asset_id', 'Image URLs and paths are forbidden.');
                }
            }
            if (! is_string($block['alt'] ?? null)) {
                $this->error('image_alt_missing', $path.'.alt', 'Image alt text must be a string.');
            } else {
                $this->text($block['alt'], $path.'.alt');
            }
            if (! array_key_exists('width', $block)) {
                $this->error('image_width_missing', $path.'.width', 'Image width is required.');
            } else {
                $this->dimension($block['width'], $path.'.width');
            }
            if (array_key_exists('height', $block)) {
                $this->number($block['height'], 1, 20000000, $path.'.height');
                $width = is_int($block['width'] ?? null) ? $block['width'] : 600;
                if (is_int($block['height']) && $width * $block['height'] > 20000000) {
                    $this->error('invalid_dimension', $path, 'Image fallback dimensions exceed the 20-megapixel raster bound.');
                }
            }
        } elseif ($type === 'divider') {
            if (array_key_exists('color', $block)) {
                $this->color($block['color'], $path.'.color');
            }
        } else {
            $this->number($block['height'] ?? null, 0, 96, $path.'.height');
        }
    }

    private function table(array $block, string $path, int $depth): void
    {
        $rows = $block['rows'] ?? null;
        if (! is_array($rows) || ! array_is_list($rows) || $rows === []) {
            $this->error('table_rows_not_array', $path.'.rows', 'Table rows must be a nonempty ordered list.');

            return;
        }
        $columns = null;
        if (array_key_exists('headers', $block)) {
            if (! is_array($block['headers']) || ! array_is_list($block['headers']) || $block['headers'] === []) {
                $this->error('invalid_table_headers', $path.'.headers', 'Headers must be a nonempty ordered list.');
            } else {
                $columns = count($block['headers']);
                foreach ($block['headers'] as $index => $cell) {
                    $this->cell($cell, $path.'.headers['.$index.']', $depth + 1, true);
                }
            }
        }
        foreach ($rows as $index => $row) {
            $this->blocks++;
            if (is_array($row) && ! array_is_list($row)) {
                $this->keys($row, ['cells'], $path.'.rows['.$index.']');
                $row = $row['cells'] ?? null;
            }
            if (! is_array($row) || ! array_is_list($row) || $row === []) {
                $this->error('table_row_not_array', $path.'.rows['.$index.']', 'A row must contain ordered cells.');

                continue;
            }
            $columns ??= count($row);
            if (count($row) !== $columns) {
                $this->error('table_not_rectangular', $path.'.rows['.$index.']', 'Simple tables must be rectangular.');
            }
            foreach ($row as $column => $cell) {
                $this->cell($cell, $path.'.rows['.$index.']['.$column.']', $depth + 1, true);
            }
        }
    }

    private function cell(mixed $cell, string $path, int $depth, bool $inTable): void
    {
        $this->blocks++;
        if (is_string($cell)) {
            $this->text($cell, $path);
        } elseif (! is_array($cell)) {
            $this->error('invalid_cell', $path, 'A cell or list item must contain semantic text.');
        } elseif (array_is_list($cell)) {
            $this->runs($cell, $path);
        } elseif (isset($cell['type'])) {
            if ($inTable && $cell['type'] === 'table') {
                $this->error('nested_table_not_supported', $path, 'Nested tables are not supported.');
            } else {
                $this->block($cell, $path, $depth);
            }
        } elseif (isset($cell['blocks'])) {
            $this->keys($cell, ['blocks'], $path);
            if (! is_array($cell['blocks']) || ! array_is_list($cell['blocks'])) {
                $this->error('invalid_cell_blocks', $path, 'Cell blocks must be ordered.');
            } else {
                foreach ($cell['blocks'] as $index => $child) {
                    if ($inTable && is_array($child) && ($child['type'] ?? '') === 'table') {
                        $this->error('nested_table_not_supported', $path, 'Nested tables are not supported.');
                    } else {
                        $this->block($child, $path.'.blocks['.$index.']', $depth + 1);
                    }
                }
            }
        } else {
            $this->keys($cell, ['text', 'runs'], $path);
            $this->content($cell, $path);
        }
    }

    private function content(array $value, string $path): void
    {
        if (array_key_exists('text', $value) === array_key_exists('runs', $value)) {
            $this->error('invalid_text_union', $path, 'Use exactly one of text or runs.');
        } elseif (array_key_exists('runs', $value)) {
            $this->runs($value['runs'], $path.'.runs');
        } else {
            $this->text($value['text'], $path.'.text');
        }
    }

    private function runs(mixed $runs, string $path): void
    {
        if (! is_array($runs) || ! array_is_list($runs)) {
            $this->error('runs_not_array', $path, 'Runs must be an ordered list.');

            return;
        }
        foreach ($runs as $index => $run) {
            $at = $path.'['.$index.']';
            if (is_string($run)) {
                $this->text($run, $at);

                continue;
            }
            if (! is_array($run) || array_is_list($run)) {
                $this->error('invalid_run', $at, 'A text run must be an object or string.');

                continue;
            }
            $this->keys($run, self::ALLOWED_RUN_KEYS, $at, 'unsupported_run_property');
            if (array_key_exists('text', $run) === array_key_exists('token', $run)) {
                $this->error('invalid_run_union', $at, 'Use exactly one of text or token.');
            } elseif (array_key_exists('token', $run)) {
                $this->token($run['token'], $at.'.token');
                $this->characters += is_string($run['token']) ? mb_strlen($run['token']) : 0;
            } else {
                $this->text($run['text'], $at.'.text');
            }
            foreach (['bold', 'italic', 'underline'] as $flag) {
                if (array_key_exists($flag, $run) && ! is_bool($run[$flag])) {
                    $this->error('invalid_'.$flag.'_format', $at.'.'.$flag, 'Formatting flags must be boolean.');
                }
            }
            if (array_key_exists('color', $run)) {
                $this->color($run['color'], $at.'.color');
            }
            if (array_key_exists('link', $run) && array_key_exists('href', $run)) {
                $this->error('invalid_link_union', $at, 'Use only one link field.');
            }
            $url = $run['link'] ?? $run['href'] ?? null;
            if ((array_key_exists('link', $run) || array_key_exists('href', $run)) && $url === null) {
                $this->error('unsafe_link_scheme', $at, 'Link fields must contain a typed URL.');
            }
            if ($url !== null) {
                if (is_string($url)) {
                    $this->text($url, $at.'.link');
                    $wholeToken = preg_match('/^\{\{([A-Za-z0-9_.]+_url)\}\}$/', $url, $match)
                        && in_array($match[1], $this->tokens, true);
                    $probe = preg_replace('/\{\{[^{}]+\}\}/', 'token', $url);
                } else {
                    $wholeToken = false;
                    $probe = '';
                }
                if (! $wholeToken && ! NoticeEmailLinkPolicy::allows($probe)) {
                    $this->error('unsafe_link_scheme', $at, 'Use HTTPS, the configured local application origin in local/testing, or an exact catalogued URL token.');
                }
            }
        }
    }

    private function text(mixed $text, string $path): void
    {
        if (! is_string($text)) {
            $this->error('text_not_string', $path, 'Authored text must be a string.');

            return;
        }
        $this->characters += mb_strlen($text);
        if (preg_match('/<\s*(script|style)\b/i', $text)) {
            $this->error('raw_script_rejected', $path, 'Executable markup is forbidden.');
        } elseif (preg_match('/<\/?[A-Za-z][^>]*>/', $text)) {
            $this->error('raw_html_rejected', $path, 'Use semantic runs instead of raw HTML.');
        }
        preg_match_all('/\{\{([^{}]*)\}\}/', $text, $matches);
        foreach ($matches[1] as $token) {
            $this->token(trim($token), $path);
        }
        $remainder = preg_replace('/\{\{[^{}]*\}\}/', '', $text);
        if (str_contains($remainder, '{{') || str_contains($remainder, '}}') || str_contains($text, '{{{') || str_contains($text, '}}}')) {
            $this->error('unbalanced_token_delimiters', $path, 'Malformed token delimiters.');
        }
    }

    private function token(mixed $token, string $path): void
    {
        if (! is_string($token) || ! preg_match('/^[A-Za-z0-9_.]+$/', $token)) {
            $this->error('invalid_token_name', $path, 'Malformed catalog token.');
        } elseif (! in_array($token, $this->tokens, true)) {
            $this->error('unknown_token', $path, 'Token is not in the server-owned catalog for this notice.');
        }
    }

    private function style(mixed $style, string $path): void
    {
        if (! is_array($style) || ($style !== [] && array_is_list($style))) {
            $this->error('invalid_style', $path, 'Style must be an allowlisted object.');

            return;
        }
        $this->keys($style, self::STYLE_KEYS, $path);
        foreach ($style as $key => $value) {
            if ($key === 'font_family' && ! in_array($value, self::FONTS, true)) {
                $this->error('invalid_font', $path.'.'.$key, 'Font is not allowlisted.');
            } elseif ($key === 'font_size') {
                $this->number($value, 8, 72, $path.'.'.$key);
            } elseif (in_array($key, ['color', 'background_color'], true)) {
                $this->color($value, $path.'.'.$key);
            } elseif ($key === 'align') {
                $this->alignment($value, $path.'.'.$key);
            } elseif (in_array($key, ['spacing_before', 'spacing_after', 'padding'], true)) {
                $this->number($value, 0, 96, $path.'.'.$key);
            } elseif ($key === 'width') {
                $this->dimension($value, $path.'.'.$key);
            }
        }
    }

    private function dimension(mixed $value, string $path): void
    {
        if (is_string($value) && preg_match('/^(100|[1-9][0-9]?)%$/', $value)) {
            return;
        }
        $this->number($value, 1, 600, $path);
    }

    private function number(mixed $value, int $min, int $max, string $path): void
    {
        if (! is_int($value) || $value < $min || $value > $max) {
            $this->error('invalid_dimension', $path, 'Use a bounded integer pixel dimension.');
        }
    }

    private function alignment(mixed $value, string $path): void
    {
        if (! in_array($value, self::ALLOWED_ALIGNMENTS, true)) {
            $this->error('invalid_alignment', $path, 'Unsupported alignment.');
        }
    }

    private function color(mixed $value, string $path): void
    {
        if (! is_string($value) || ! preg_match('/^#(?:[A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $value)) {
            $this->error('invalid_hex_color', $path, 'Use a three- or six-digit hex colour.');
        }
    }

    private function keys(array $value, array $allowed, string $path, string $code = 'unsupported_property'): void
    {
        foreach (array_diff(array_keys($value), $allowed) as $key) {
            $this->error($code, $path.'.'.$key, 'Unknown schema property.');
            if (in_array($key, ['src', 'url'], true)) {
                $this->error('image_external_url_rejected', $path.'.'.$key, 'External source fields are forbidden.');
            }
        }
    }

    private function error(string $code, string $path, string $message): void
    {
        $this->errors[] = compact('code', 'message', 'path');
    }

    private static function catalogTokens(): array
    {
        $tokens = [];
        foreach (['admissions', 'examination', 'results'] as $domain) {
            foreach (CommunicationTemplateRegistry::slotsFor($domain) as $slot) {
                foreach ($slot['tokens'] as $token) {
                    $tokens[] = trim($token['token'], '{} ');
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    public static function defaultBlueprint(): array
    {
        return ['schema_version' => self::SCHEMA_VERSION, 'layout' => self::LAYOUT,
            'blocks' => [['type' => 'paragraph', 'runs' => [['text' => 'Dear {{applicant_name}},']]],
                ['type' => 'paragraph', 'text' => 'This is an official notice regarding your application.']]];
    }
}
