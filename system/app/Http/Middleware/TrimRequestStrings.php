<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\TrimStrings;

final class TrimRequestStrings extends TrimStrings
{
    protected function transform(mixed $key, mixed $value): mixed
    {
        if ($key === 'reason' && request()->isMethod('POST')
            && preg_match('#^setup/admission-support/programs/[^/]+/cycles/[^/]+/advising-note/(?:draft|restore|copy|publish|retract)$#D', request()->path()) === 1) {
            return $value;
        }

        return parent::transform($key, $value);
    }
}
