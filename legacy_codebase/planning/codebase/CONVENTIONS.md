# Coding Conventions

**Analysis Date:** 2026-04-13

## Naming Patterns

**Files:**
- Controllers: `Verb	NounController.php` (e.g., `ExamSessionController.php`)
- Models: `Single	.php` (e.g., `User.php`, `Applicant.php`)
- Services: `Noun	Service.php` (e.g., `ScoreInputService.php`)
- Requests: `Verb	NounRequest.php` (e.g., `StoreApplicationRequest.php`)
- Factories: `ModelFactory.php` (e.g., `UserFactory.php`)
- Test files: `Test.php` suffix (e.g., `LoginTest.php`, `ScoreInputServiceTest.php`)
- Svelte pages: `PascalCase.svelte` (e.g., `Login.svelte`, `Dashboard.svelte`)

**Functions/Methods:**
- camelCase for methods and functions (e.g., `saveScores()`, `hasRole()`)
- Descriptive verbs for actions: `save`, `update`, `delete`, `create`, `fetch`, `upsert`
- Boolean methods prefixed with `is`, `has`, `can`, `should` (e.g., `isAuthenticated()`, `hasRole()`)

**Variables:**
- camelCase (e.g., `$gradingSession`, `$applicantScore`)
- Use descriptive names, avoid single letters except in loops

**Types (PHP):**
- TitleCase for classes, interfaces, traits (e.g., `User`, `BelongsToMany`)
- Enum keys: TitleCase (e.g., `StatusEnum::Active`)

**Constants:**
- SCREAMING_SNAKE_CASE (e.g., `PROVIDER_GOOGLE`)

## Code Style

**PHP (using Laravel conventions):**
- PSR-12 formatting via Laravel Pint
- 4-space indentation (editorconfig: `indent_size = 4`)
- Strict types: `declare(strict_types=1);` in new files
- Use PHP 8 constructor property promotion
- Return type declarations on all methods
- PHPDoc blocks for complex logic and public APIs

**Svelte:**
- Svelte 5 with runes (`$state`, `$derived`, `$props`)
- 2-space indentation
- Use `bind:value` for form inputs
- Inertia.js for routing: `useForm`, `Link`, `usePage`

**Tailwind CSS:**
- Tailwind v4 utility classes
- Custom CSS via `app.css` with theme() function
- Use shadcn-svelte components from `@/Components/ui/`

**Formatting:**
- Laravel Pint runs via `vendor/bin/pint --dirty --format agent`
- EditorConfig enforces 4-space PHP, 2-space YAML

## Import Organization

**PHP (Laravel order):**
1. Framework imports (`Illuminate\Foundation\...`)
2. External packages (`Symfony\...`, `Laravel\...`)
3. Application imports (`App\Models\...`, `App\Services\...`)
4. Database/Factories (`Database\Seeders\...`)
5. Tests (`Tests\TestCase`)

**Svelte:**
1. Svelte built-ins (`svelte`, `@sveltejs/...`)
2. Inertia (`@inertiajs/svelte`)
3. Layouts (`@/Layouts/...`)
4. Components (`@/Components/...`)
5. Other pages (`@/Pages/...`)

**Path Aliases:**
- PHP: No common aliases (use `App\Models\User`)
- Svelte: `@` maps to `resources/js/`

## Error Handling

**PHP:**
- Throw exceptions for exceptional states
- Use Laravel FormRequest for validation
- Return validated DTOs, not raw request input
- Log detailed error context for debugging
- Never silently swallow errors

**Svelte:**
- Display errors from `$page.props.errors`
- Use flash messages for feedback (`$page.props.flash`)
- Show inline validation errors under inputs

**API Responses:**
- Use `assertSessionHasErrors()` in tests
- Use `redirect()->withErrors()` in controllers

## Logging

**Framework:** Laravel's Log facade (`Log::info()`, `Log::error()`)

**Patterns:**
- Log important business events (authentication, score changes)
- Log exceptions with context
- Use appropriate log levels: debug, info, warning, error, critical

## Comments

**When to Comment:**
- Complex business logic
- Non-obvious workarounds
- Permission/authorization checks
- TODO/FIXME for tracked issues

**Avoid:**
- Inline comments for obvious code
- Redundant PHPDoc on simple getters
- Commented-out code (delete instead)

## Function Design

**Size:** Keep functions under 50 lines

**Parameters:**
- Use typed parameters
- Default values when appropriate
- Use DTOs for related parameters

**Return Values:**
- Explicit return types required
- Never return `false`/`null` as error channels

## Module Design

**PHP Exports:**
- Use single class per file
- Use explicit exports, avoid wildcard imports

**Svelte:**
- Co-locate related components in `Components/`
- Pages go in `Pages/{Section}/`

---

*Convention analysis: 2026-04-13*