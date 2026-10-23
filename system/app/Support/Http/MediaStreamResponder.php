<?php

namespace App\Support\Http;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpKernel\Exception\NotAcceptableHttpException;

final class MediaStreamResponder
{
    private const ALLOWED_MIME_TYPES = ['image/png', 'image/webp', 'image/jpeg'];

    public function inline(
        Request $request,
        string $bytes,
        string $mime,
        string $cacheControl,
        string $filename,
    ): Response {
        if (! in_array($mime, self::ALLOWED_MIME_TYPES, true)) {
            throw new NotAcceptableHttpException('Unsupported media type.');
        }

        $safeFilename = preg_replace('/[\x00-\x1F\x7F]/u', '', basename(str_replace('\\', '/', $filename)));
        $safeFilename = is_string($safeFilename) && $safeFilename !== '' ? $safeFilename : 'media';
        $etag = '"'.hash('sha256', $bytes).'"';
        $response = new Response($bytes, Response::HTTP_OK, [
            'Cache-Control' => $cacheControl,
            'Content-Type' => $mime,
            'Content-Length' => (string) strlen($bytes),
            'ETag' => $etag,
            'X-Content-Type-Options' => 'nosniff',
            'Vary' => 'If-None-Match',
        ]);
        $response->headers->set('Content-Disposition', HeaderUtils::makeDisposition(
            'inline',
            $safeFilename,
            'media',
        ));

        $response->isNotModified($request);

        return $response;
    }
}
