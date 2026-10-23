<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final class ApplicantOfficeInquiryResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('applicant/inquiries', 'applicant/inquiries/*', 'staff/inquiries', 'staff/inquiries/*', 'inquiries/*')) {
            return $next($request);
        }

        try {
            $response = $next($request);
        } catch (AuthenticationException) {
            return $this->error(401);
        } catch (TokenMismatchException) {
            return $this->error(419);
        } catch (ThrottleRequestsException $exception) {
            return $this->error(429, $exception->getHeaders());
        } catch (AuthorizationException) {
            return $this->error(404);
        } catch (HttpExceptionInterface $exception) {
            if (! in_array($exception->getStatusCode(), [401, 403, 404, 419, 429], true)) {
                throw $exception;
            }

            return $this->error($exception->getStatusCode() === 403 ? 404 : $exception->getStatusCode(), $exception->getHeaders());
        }

        $status = $response->getStatusCode();
        if (in_array($status, [401, 403, 404, 419, 429], true)) {
            $headers = [];
            foreach (['Retry-After', 'X-RateLimit-Limit', 'X-RateLimit-Remaining', 'X-RateLimit-Reset'] as $name) {
                if ($response->headers->has($name)) {
                    $headers[$name] = $response->headers->get($name);
                }
            }

            return $this->error($status === 403 ? 404 : $status, $headers);
        }
        if ($status >= 500) {
            return $this->error(503);
        }

        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }

    /** @param array<string, string|string[]> $headers */
    private function error(int $status, array $headers = []): Response
    {
        [$code, $message] = match ($status) {
            401 => ['session_expired', 'Sign in again to continue.'],
            419 => ['session_expired', 'Your session has expired. Refresh and try again.'],
            429 => ['rate_limited', 'Too many requests. Wait and try again.'],
            503 => ['inquiry_unavailable', 'Inquiries are temporarily unavailable. Try again later.'],
            default => ['not_found', 'Inquiry not found.'],
        };

        return response()->json(['code' => $code, 'message' => $message], $status, array_merge($headers, ['Cache-Control' => 'private, no-store']));
    }
}
