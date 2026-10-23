<?php

use App\Notifications\Templates\Email\NoticeEmailLinkPolicy;
use App\Notifications\Templates\Email\NoticeEmailRenderer;
use App\Notifications\Templates\Email\NoticeEmailSchema;

function renderNoticeEmail(array $blocks, array $context = ['applicant_name' => 'Actual Name'], array $tokens = ['applicant_name']): array
{
    return (new NoticeEmailRenderer)->render(['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => $blocks],
        $context, $tokens, 'notice-email-1', 'notice-email-1', [], 'Notice for {{applicant_name}}');
}

test('email renderer escapes actual facts and preserves full semantic layout in plaintext', function (): void {
    $result = renderNoticeEmail([
        ['type' => 'heading', 'level' => 2, 'runs' => [['text' => 'Dear {{applicant_name}}', 'bold' => true]]],
        ['type' => 'paragraph', 'text' => str_repeat('Full authored layout. ', 40), 'style' => ['font_family' => 'Georgia', 'font_size' => 18, 'spacing_after' => 24]],
        ['type' => 'list', 'ordered' => true, 'items' => ['Bring ID', ['runs' => [['text' => 'Arrive early', 'italic' => true]]]]],
        ['type' => 'table', 'headers' => ['Fact', 'Value'], 'rows' => [['Seat', ['text' => 'A-12']]]],
        ['type' => 'divider', 'color' => '#333333'], ['type' => 'spacer', 'height' => 16],
    ], ['applicant_name' => '<img src=x onerror="attack()"> & Actual']);
    expect($result['production_html'])->toContain('&lt;img src=x onerror=&quot;attack()&quot;&gt; &amp; Actual', 'max-width:600px', 'width:100%', '<th scope="col"', 'font-family:Georgia', 'font-size:18px')
        ->not->toContain('<img src=x', 'mm', '{{applicant_name}}', 'flex', 'grid');
    expect($result['subject'])->toBe('Notice for <img src=x onerror="attack()"> & Actual')
        ->and($result['text'])->toContain('1. Bring ID', '2. Arrive early', 'Fact | Value', 'Seat | A-12', str_repeat('Full authored layout. ', 39));
});

test('missing unknown malformed and recursive tokens never resolve to samples or blanks', function (array $context, string $text, string $code): void {
    expect(fn () => renderNoticeEmail([['type' => 'paragraph', 'text' => $text]], $context))->toThrow(RuntimeException::class, $code);
})->with([
    [[], '{{applicant_name}}', 'email_context_missing'],
    [['applicant_name' => ''], '{{applicant_name}}', 'email_context_missing'],
    [['applicant_name' => '{{secret}}'], '{{applicant_name}}', 'email_context_invalid'],
]);

test('pinned unavailable schema and renderer fail rather than fall forward', function (): void {
    $renderer = new NoticeEmailRenderer;
    $blueprint = NoticeEmailSchema::defaultBlueprint();
    $v1 = $renderer->render($blueprint, ['applicant_name' => 'Original'], ['applicant_name'], 'notice-email-1', 'notice-email-1');
    expect($renderer->render($blueprint, ['applicant_name' => 'Original'], ['applicant_name'], 'notice-email-1', 'notice-email-1'))->toBe($v1);
    expect(fn () => $renderer->render($blueprint, [], [], 'notice-email-2', 'notice-email-1'))->toThrow(RuntimeException::class, 'email_renderer_unavailable');
    expect(fn () => $renderer->render($blueprint, [], [], 'notice-email-1', 'notice-email-2'))->toThrow(RuntimeException::class, 'email_renderer_unavailable');
});

test('links validate actual substituted urls and preview links are inert with restrictive CSP', function (): void {
    $block = ['type' => 'paragraph', 'runs' => [['text' => 'Portal', 'link' => '{{portal_url}}']]];
    $renderer = new NoticeEmailRenderer;
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [$block]];
    expect(fn () => $renderer->render($blueprint, ['portal_url' => 'javascript:attack()'], ['portal_url'], 'notice-email-1', 'notice-email-1'))->toThrow(RuntimeException::class, 'email_link_invalid');
    $result = $renderer->render($blueprint, ['portal_url' => 'https://example.invalid/a?x=1&y=2'], ['portal_url'], 'notice-email-1', 'notice-email-1');
    $projection = $renderer->project($result['production_html'], []);
    expect($result['production_html'])->toContain('href="https://example.invalid/a?x=1&amp;y=2"')
        ->and($projection['preview_html'])->not->toContain('href=', 'allow-same-origin')->toContain('aria-disabled="true"', 'Content-Security-Policy', 'default-src', 'img-src data:')
        ->and($projection['projection_sha256'])->toBe(hash('sha256', $projection['preview_html']));
    expect(fn () => $renderer->project('<img src="cid:unknown@nexiam.inline">', []))->toThrow(RuntimeException::class, 'email_unknown_cid');
});

test('closed schema rejects executable blocks css mm and unapproved fonts without stripping', function (array $block): void {
    expect(fn () => renderNoticeEmail([$block]))->toThrow(InvalidArgumentException::class);
})->with([
    [['type' => 'raw_html', 'html' => '<script>attack()</script>']],
    [['type' => 'paragraph', 'text' => 'safe', 'style' => ['position' => 'absolute']]],
    [['type' => 'paragraph', 'text' => 'safe', 'style' => ['width' => '50mm']]],
    [['type' => 'paragraph', 'text' => 'safe', 'style' => ['font_family' => 'url(https://evil.test)']]],
    [['type' => 'paragraph', 'text' => '{{applicant_name']],
    [['type' => 'paragraph', 'text' => '{{foreign_fact}}']],
]);

test('nested list and semantic table cell blocks retain their entire markup and text', function (): void {
    $result = renderNoticeEmail([
        ['type' => 'list', 'ordered' => false, 'items' => [['blocks' => [['type' => 'paragraph', 'text' => 'Parent item']]],
            ['type' => 'list', 'ordered' => true, 'items' => ['Nested item']]]],
        ['type' => 'table', 'rows' => [[['type' => 'heading', 'level' => 3, 'text' => 'Semantic cell'], ['blocks' => [
            ['type' => 'paragraph', 'text' => 'First line'], ['type' => 'paragraph', 'text' => 'Second line'],
        ]]]]],
    ]);
    expect($result['production_html'])->toContain('<ul', '<ol', '<h3', 'First line', 'Second line')
        ->and($result['text'])->toContain('Parent item', '1. Nested item', 'Semantic cell', "First line\nSecond line");
});

test('local application portal URL preserves its actual HTTP origin in schema production and plaintext without fetching', function (string $environment): void {
    $previousEnvironment = app()->environment();
    app()->instance('env', $environment);
    config(['app.url' => 'http://localhost:18000']);
    $url = 'http://localhost:18000/applicant/schedule?view=upcoming';
    $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
        ['type' => 'paragraph', 'runs' => [['text' => 'Open actual schedule', 'link' => '{{portal_url}}']]],
        ['type' => 'paragraph', 'runs' => [['text' => 'Local application', 'href' => $url]]],
    ]];
    try {
        NoticeEmailSchema::assertValid($blueprint, ['portal_url']);
        $renderer = new NoticeEmailRenderer;
        $rendered = $renderer->render($blueprint, ['portal_url' => $url], ['portal_url'], 'notice-email-1', 'notice-email-1');
        expect($rendered['production_html'])->toContain('href="'.$url.'"')->not->toContain('https://localhost')
            ->and($rendered['text'])->toContain($url);
        expect($renderer->project($rendered['production_html'], [])['preview_html'])->not->toContain('href=');
    } finally {
        app()->instance('env', $previousEnvironment);
    }
})->with(['local', 'testing']);

test('local HTTP allowance rejects foreign origins aliases ports credentials and protocol relative links', function (string $url): void {
    $previousEnvironment = app()->environment();
    app()->instance('env', 'local');
    config(['app.url' => 'http://localhost:18000']);
    try {
        expect(NoticeEmailLinkPolicy::allows($url))->toBeFalse();
        $blueprint = ['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
            ['type' => 'paragraph', 'runs' => [['text' => 'Untrusted link', 'link' => '{{portal_url}}']]],
        ]];
        expect(fn () => (new NoticeEmailRenderer)->render($blueprint, ['portal_url' => $url], ['portal_url'], 'notice-email-1', 'notice-email-1'))
            ->toThrow(RuntimeException::class, 'email_link_invalid');
    } finally {
        app()->instance('env', $previousEnvironment);
    }
})->with([
    'http://foreign.example.test/applicant',
    'http://localhost.example.test:18000/applicant',
    'http://sub.localhost:18000/applicant',
    'http://localhost:18001/applicant',
    'http://127.0.0.1:18000/applicant',
    'http://10.0.0.1:18000/applicant',
    'http://user:password@localhost:18000/applicant',
    '//localhost:18000/applicant',
    'http://localhost:18000\\@foreign.example.test/applicant',
]);

test('production still rejects exact configured application HTTP and remote configured HTTP cannot enable local exception', function (): void {
    $previousEnvironment = app()->environment();
    try {
        app()->instance('env', 'production');
        config(['app.url' => 'http://localhost:18000']);
        expect(NoticeEmailLinkPolicy::allows('http://localhost:18000/applicant'))->toBeFalse()
            ->and(NoticeEmailLinkPolicy::allows('https://example.test/applicant'))->toBeTrue();
        expect(fn () => NoticeEmailSchema::assertValid(['schema_version' => 'notice-email-1', 'layout' => 'flow', 'blocks' => [
            ['type' => 'paragraph', 'runs' => [['text' => 'HTTP rejected', 'link' => 'http://localhost:18000/applicant']]],
        ]]))->toThrow(InvalidArgumentException::class);
        app()->instance('env', 'local');
        config(['app.url' => 'http://10.0.0.1:18000']);
        expect(NoticeEmailLinkPolicy::allows('http://10.0.0.1:18000/applicant'))->toBeFalse();
    } finally {
        app()->instance('env', $previousEnvironment);
    }
});
