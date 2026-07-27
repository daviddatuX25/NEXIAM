# Technology Stack

**Analysis Date:** 2026-04-13

## Languages

**Primary:**
- PHP 8.2 - Backend API and server-side logic
- JavaScript - Client-side interactivity

**Secondary:**
- TypeScript - Frontend type checking (via Vite/Svelte)
- Svelte 5 - UI components (Inertia adapter)

## Runtime

**Environment:**
- PHP 8.2 (Laravel 12 requires PHP 8.2+)
- Node.js 20+ (via npm for frontend)

**Package Manager:**
- Composer (PHP)
- npm (JavaScript)

## Frameworks

**Core:**
- Laravel 12 - Backend framework
- Inertia.js v2 - Server-side rendering with SPA feel

**Frontend:**
- Svelte 5 - UI component framework
- Tailwind CSS v4 - Styling

**Testing:**
- PHPUnit 11.5.3 - Backend testing
- Laravel Boost - Development tools

**Build/Dev:**
- Vite 7 - Frontend bundler
- Laravel Vite Plugin - Laravel integration
- Laravel Pint - Code formatting

## Key Dependencies

**Critical:**
- `inertiajs/inertia-laravel` v2 - Inertia server adapter
- `@inertiajs/svelte` v2 - Inertia Svelte adapter
- `barryvdh/laravel-dompdf` v3 - PDF generation
- `phpoffice/phpword` v1 - Word document generation
- `endroid/qr-code` v5 - QR code generation

**AI & External Services:**
- `moe-mizrak/laravel-openrouter` v2 - OpenRouter API wrapper
- `laravel/socialite` v5 - OAuth authentication

**Security/Utilities:**
- `mews/purifier` v3 - HTML sanitization

## Configuration

**Environment:**
- `.env` file for all configuration
- `.env.example` as template
- Key configs: APP_URL, DB_*, QUEUE_*, OPENROUTER_*, MIXEDBREAD_*

**Build:**
- `vite.config.js` - Vite configuration with Svelte, Tailwind, Laravel plugins
- `tsconfig.json` - TypeScript configuration (inferred by Svelte)

## Platform Requirements

**Development:**
- Docker support via Laravel Sail
- Node.js 20+
- PHP 8.2+

**Production:**
- PHP 8.2+
- MySQL 8.0+ or MariaDB
- Queue worker (database driver)
- Storage for file uploads

---

*Stack analysis: 2026-04-13*