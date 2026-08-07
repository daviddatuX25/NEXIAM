# NEXIAM System

The executable greenfield target for NEXIAM. The legacy Laravel/Inertia/Svelte runtime remains under `../legacy_codebase/application/` as read-only behavior and technical evidence; it is not imported as target authority. The target runtime is Laravel 13 + Inertia 3 + Svelte 5.

## Runtime baseline

- PHP 8.4 and Laravel 13
- Inertia Laravel 3 with Svelte 5
- TypeScript 5.9, Vite, and Tailwind 4
- PostgreSQL 16 as the authoritative local database
- Pest 5 with Laravel, architecture, and browser plugins
- Laravel Boost 2.4 for codebase-aware development assistance
- Docker Compose services for app, queue, scheduler, PostgreSQL, and Mailpit

## Local verification

Run from this directory:

```sh
docker compose up -d --wait postgres mailpit
docker compose run --rm --no-deps app php artisan migrate --force
docker compose up -d --wait app queue scheduler
```

The local app is exposed at `http://127.0.0.1:18000/`; health is available at `/health`, and Mailpit is exposed at `http://127.0.0.1:18025/`.

Quality commands are executed in Dockerized PHP/Node environments:

```sh
php artisan test --compact
vendor/bin/pint --dirty --format agent
vendor/bin/phpstan analyse --memory-limit=1G
npm run build
npm run quality
npm run test:browser
```

The target follows the Founder Decision → Greenfield Foundation → OpenSpec → implementation/test evidence chain.

**Current gate:** E0 scaffold evidence is recorded at [`../docs/greenfield-foundation/evidence/e0-scaffold-evidence.md`](../docs/greenfield-foundation/evidence/e0-scaffold-evidence.md). E1 OpenSpec package is open at [`../openspec/changes/e1-identity-reference-foundation/`](../openspec/changes/e1-identity-reference-foundation/); E2, pilot, and production remain **NO-GO** until their own PASS evidence.

See the master roadmap: [`../docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md`](../docs/greenfield-foundation/14-readiness-gates-and-reconciled-roadmap.md) and the active checklist: [`../openspec/changes/e0-greenfield-foundation/tasks.md`](../openspec/changes/e0-greenfield-foundation/tasks.md).

<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
