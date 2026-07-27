<?php

namespace App\Support;

class GoogleOAuthConfig
{
    /**
     * Returns true only when Google OAuth credentials are fully configured.
     * Used to gate route registration and UI rendering.
     */
    public static function isConfigured(): bool
    {
        return ! empty(config('services.google.client_id'))
            && ! empty(config('services.google.client_secret'));
    }
}