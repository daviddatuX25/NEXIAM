# External Integrations

**Analysis Date:** 2026-04-13

## APIs & External Services

**AI Services:**
- OpenRouter - AI exam scheduling assistant
  - SDK/Client: `moe-mizrak/laravel-openrouter` package
  - Auth: `OPENROUTER_API_KEY` (env var)
  - Endpoint: Uses OpenRouter's free models

- Mixedbread - RAG embeddings for AI Companion
  - SDK/Client: Direct HTTP client (configured in `config/services.php`)
  - Auth: `MIXEDBREAD_API_KEY`, `MIXEDBREAD_STORE_ID` (env vars)
  - Base URL: `MIXEDBREAD_BASE_URL` (default: `https://api.mixedbread.com/v1`)

**Authentication:**
- Google OAuth - User login via Google
  - SDK/Client: `laravel/socialite`
  - Auth: `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (env vars)
  - Callback: `/auth/google/callback`

## Data Storage

**Database:**
- MySQL/MariaDB
  - Connection: `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
  - Client: Laravel's PDO/MySQL

**File Storage:**
- Local filesystem only (`FILESYSTEM_DISK=local`)
- Files stored in `storage/app/public/`

**Caching:**
- Database driver (`CACHE_STORE=database`)
- No Redis used (config present but not default)

## Authentication & Identity

**Auth Provider:**
- Laravel built-in (session-based)
- Google OAuth via Socialite (optional, configured but not required)
- No other providers detected

**Session Configuration:**
- Driver: `SESSION_DRIVER=database`
- Lifetime: `SESSION_LIFETIME=120` minutes (configurable)

## Monitoring & Observability

**Error Tracking:**
- None detected (no external service integrated)

**Logs:**
- Laravel stack log channel (`LOG_CHANNEL=stack`)
- Configured in `config/logging.php`

## CI/CD & Deployment

**Hosting:**
- Laravel Sail (Docker) - Development environment
- Standard PHP hosting for production

**CI Pipeline:**
- None detected (no GitHub Actions, GitLab CI, etc.)

## Environment Configuration

**Required env vars:**
- `APP_KEY` - Laravel application key
- `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`
- `SESSION_DRIVER`, `SESSION_LIFETIME`
- `QUEUE_CONNECTION`

**Optional env vars:**
- `OPENROUTER_API_KEY` - For AI scheduling assistant
- `MIXEDBREAD_API_KEY`, `MIXEDBREAD_STORE_ID` - For AI Companion
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` - For OAuth login

**Secrets location:**
- `.env` file in project root
- Never committed to version control

## Webhooks & Callbacks

**Incoming:**
- Google OAuth callback: `/auth/google/callback` (route: `auth.google.callback`)

**Outgoing:**
- None detected

## Additional Services (Configured but Optional)

- AWS SES (`AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`) - Email provider
- Postmark (`POSTMARK_API_KEY`) - Email provider
- Resend (`RESEND_API_KEY`) - Email provider
- Slack (`SLACK_BOT_USER_OAUTH_TOKEN`) - Notifications

---

*Integration audit: 2026-04-13*