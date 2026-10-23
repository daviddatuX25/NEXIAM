<?php

use App\Support\Http\MediaStreamResponder;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

function mediaStreamCacheControlDirectives(string $header): array
{
    $directives = array_map(
        static fn (string $directive): string => strtolower(trim($directive)),
        explode(',', $header),
    );

    sort($directives);

    return $directives;
}

function mediaStreamGetRequest(array $server = []): Request
{
    return Request::create('/', 'GET', [], [], [], $server);
}

test('it streams JPEG bytes with required media and cache headers', function (): void {
    $bytes = "\xff\xd8\xffexample-image";
    $cacheControl = 'private, max-age=31536000, immutable';
    $response = (new MediaStreamResponder)->inline(
        mediaStreamGetRequest(),
        $bytes,
        'image/jpeg',
        $cacheControl,
        'photo.jpg',
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe($bytes)
        ->and($response->headers->get('Content-Type'))->toBe('image/jpeg')
        ->and($response->headers->get('ETag'))->toBe('"'.hash('sha256', $bytes).'"')
        ->and(mediaStreamCacheControlDirectives($response->headers->get('Cache-Control')))->toBe(mediaStreamCacheControlDirectives($cacheControl))
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

test('it streams allowed image bytes with an exact cache policy and safe inline headers', function (): void {
    $bytes = "\x89PNG\r\n\x1a\nexample-image";
    $cacheControl = 'private, max-age=31536000, immutable';
    $response = (new MediaStreamResponder)->inline(
        mediaStreamGetRequest(),
        $bytes,
        'image/png',
        $cacheControl,
        "../unsafe\"name.png\r\nX-Injected: yes",
    );

    expect($response->getStatusCode())->toBe(200)
        ->and($response->getContent())->toBe($bytes)
        ->and($response->headers->get('Content-Type'))->toBe('image/png')
        ->and($response->headers->get('Content-Length'))->toBe((string) strlen($bytes))
        ->and($response->headers->get('ETag'))->toBe('"'.hash('sha256', $bytes).'"')
        ->and(mediaStreamCacheControlDirectives($response->headers->get('Cache-Control')))->toBe(mediaStreamCacheControlDirectives($cacheControl))
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('Vary'))->toBe('If-None-Match')
        ->and($response->headers->get('Content-Disposition'))->toStartWith('inline;')
        ->and($response->headers->get('Content-Disposition'))->not->toContain("\r", "\n", '../', 'X-Injected');
});

test('it returns not modified for a matching ETag', function (): void {
    $bytes = 'image bytes';
    $etag = '"'.hash('sha256', $bytes).'"';
    $request = mediaStreamGetRequest(['HTTP_IF_NONE_MATCH' => $etag]);

    $response = (new MediaStreamResponder)->inline(
        $request,
        $bytes,
        'image/webp',
        'private, no-store',
        'preview.webp',
    );

    expect($response->getStatusCode())->toBe(304)
        ->and($response->getContent())->toBe('')
        ->and($response->headers->get('ETag'))->toBe($etag)
        ->and(mediaStreamCacheControlDirectives($response->headers->get('Cache-Control')))->toBe(mediaStreamCacheControlDirectives('private, no-store'));
});

test('it rejects media types outside the image allowlist', function (string $mime, string $filename): void {
    expect(fn () => (new MediaStreamResponder)->inline(
        mediaStreamGetRequest(),
        'bytes',
        $mime,
        'private, no-store',
        $filename,
    ))->toThrow(NotAcceptableHttpException::class);
})->with([
    ['image/gif', 'image.gif'],
    ['application/pdf', 'document.pdf'],
]);
