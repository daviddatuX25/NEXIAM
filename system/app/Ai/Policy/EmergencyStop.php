<?php

namespace App\Ai\Policy;

final class EmergencyStop
{
    public function active(): bool
    {
        return (bool) config('nexi.emergency_stop', false);
    }
}
