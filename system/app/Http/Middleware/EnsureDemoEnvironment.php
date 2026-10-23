<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureDemoEnvironment
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment(['local', 'testing']), 403, 'Demo controls are unavailable in this environment.');

        return $next($request);
    }
}
