<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Auth\Support\StaffSurface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffSurface
{
    public function __construct(private readonly StaffSurface $staffSurface) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $mode = 'authenticated'): Response
    {
        if ($mode === 'allow-guest' && $request->user() === null) {
            return $next($request);
        }

        abort_unless($this->staffSurface->allows($request), 403);

        return $next($request);
    }
}
