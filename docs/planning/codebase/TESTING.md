# Testing Patterns

**Analysis Date:** 2026-04-13

## Test Framework

**Runner:**
- PHPUnit v11
- Config: `phpunit.xml`

**Assertion Library:**
- PHPUnit assertions (`$this->assertEquals()`, `assertDatabaseHas()`, etc.)

**Run Commands:**
```bash
php artisan test --compact                      # Run all tests
php artisan test --compact tests/Feature/        # Feature tests only
php artisan test --compact tests/Unit/            # Unit tests only
php artisan test --compact --filter=testName       # Run specific test
php artisan test --compact --coverage-text       # With coverage
```

## Test File Organization

**Location:**
- Feature tests: `tests/Feature/`
- Unit tests: `tests/Unit/`
- Tests are co-located by feature area (e.g., `tests/Feature/Auth/LoginTest.php`)

**Naming:**
- Test classes: `{Feature}Test.php` (e.g., `LoginTest.php`)
- Test methods: `test_{description}_(): void`

**Structure:**
```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── LoginTest.php
│   │   └── GoogleSignInTest.php
│   ├── Admin/
│   │   ├── ExamSessionValidationTest.php
│   │   └── AcademicYearControllerTest.php
│   └── ...
└── Unit/
    └── Services/
        ├── ScoreInputServiceTest.php
        └── AuditServiceTest.php
```

## Test Structure

**Suite Organization:**
```php
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Setup test data
    }

    public function test_login_page_renders(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Auth/Login'));
    }

    public function test_login_with_valid_credentials_redirects_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'staff@example.com',
        ]);

        $response = $this->post(route('login'), [
            'email' => 'staff@example.com',
            'password' => 'password',
            'remember' => false,
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
    }
}
```

**Patterns:**
- Setup in `setUp()` method
- Use factories for test data
- Arrange-Act-Assert pattern
- Test one behavior per method

## Mocking

**Framework:** PHPUnit native (mockery available but not heavily used)

**Patterns:**
- Use factories (`User::factory()->create()`) instead of mocks for model tests
- Use `$this->actingAs($user)` for authenticated tests
- Use `$this->withoutMiddleware()` when testing middleware

**What to Mock:**
- External services (OAuth providers, AI APIs)
- Third-party SDKs

**What NOT to Mock:**
- Eloquent models (use factories)
- Laravel basics
- Internal services

## Fixtures and Factories

**Test Data:**
```php
// Using factories
$user = User::factory()->create();
$applicant = Applicant::factory()->create();
$gradingSession = GradingSession::factory()->create();

// With overrides
$user = User::factory()->create([
    'email' => 'staff@example.com',
]);

// Building without saving
$user = User::factory()->make(['email' => 'test@example.com']);
```

**Location:**
- Factories: `database/factories/{Model}Factory.php`
- Modeled after: `Illuminate\Database\Eloquent\Factories\Factory`

**Custom States:**
```php
// In factory
public function function registered(): static
{
    return $this->state(fn (array $attributes) => [
        'registered_at' => now(),
    ]);
}

// In test
$user = User::factory()->registered()->create();
```

## Coverage

**Requirements:** No explicit coverage threshold enforced

**View Coverage:**
```bash
php artisan test --coverage-html coverage/
```

**CI Note:** No coverage enforcement currently in place

## Test Types

**Feature Tests:**
- Full HTTP request/response cycle
- Use `$this->get()`, `$this->post()`, etc.
- Test Inertia responses: `assertInertia()`
- Test redirects: `assertRedirect()`
- Test session errors: `assertSessionHasErrors()`

**Unit Tests:**
- Test services in isolation
- Direct method calls
- Database assertions: `assertDatabaseHas()`, `assertDatabaseMissing()`

## Common Patterns

**Authentication:**
```php
$this->actingAs($user)->get(route('dashboard'));
```

**Authorization:**
```php
$response = $this->actingAs($user)->delete(route('admin.users.destroy', $user->id));
$response->assertForbidden();
```

**Database Refresh:**
```php
use Illuminate\Foundation\Testing\RefreshDatabase;

class SomeTest extends TestCase
{
    use RefreshDatabase;
    // Database is fresh for each test
}
```

**Form Requests:**
```php
$response = $this->post(route('route'), [
    'field' => 'value',
]);
$response->assertSessionHasErrors('field');
```

**Inertia Testing:**
```php
$response->assertInertia(fn ($page) => $page
    ->component('Component/Name')
    ->props(['key' => 'value'])
);
```

## Async Testing

**Pattern:** Tests run synchronously (PHPUnit handles this)

**Database Transactions:**
Use `RefreshDatabase` trait to wrap each test in a transaction that rolls back.

## Error Testing

**Pattern:**
```php
$response = $this->post(route('route'), [
    'invalid' => 'data',
]);

$response->assertSessionHasErrors(['field']);
$this->assertGuest();
```

---

*Testing analysis: 2026-04-13*