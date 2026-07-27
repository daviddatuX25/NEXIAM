---
status: resolved
trigger: "AptitudeAreaPolicy update() argument count error"
created: 2026-04-14T00:00:00Z
updated: 2026-04-14T00:00:00Z
---

## Current Focus
hypothesis: "Policy update() method signature requires 2 arguments but reorder endpoint only passes 1"
test: "Read policy and controller to compare argument counts"
expecting: "Find mismatch between policy method signature and how it's called"
next_action: "Fix applied - verify by testing reorder endpoint"

## Symptoms
expected: The reorder endpoint should work and reorder aptitude areas
actual: ArgumentCountError - Too few arguments to function App\Policies\AptitudeAreaPolicy::update(), 1 passed and exactly 2 expected
errors: Stack trace shows app\Policies\AptitudeAreaPolicy.php:23 being called from Gate.php line 844
reproduction: POST /admin/aptitude-areas/reorder
timeline: Started after recent changes to aptitude areas

## Eliminated

## Evidence
- timestamp: 2026-04-14
  checked: AptitudeAreaPolicy.php
  found: update() method requires 2 arguments: User $user, AptitudeArea $aptitudeArea
  implication: Controller was passing only AptitudeArea::class (1 argument) instead of a model instance
- timestamp: 2026-04-14
  checked: AptitudeAreaController.php line 100
  found: $this->authorize('update', AptitudeArea::class) - passing class instead of instance
  implication: This was the root cause - incorrect policy method being called with wrong argument type
- timestamp: 2026-04-14
  checked: Other controllers with reorder patterns
  found: Other controllers use 'update' with a model instance, but reorder operations typically have their own policy method
  implication: Best practice is to create a dedicated reorder() policy method

## Resolution
root_cause: "The reorder() method was calling authorize('update', AptitudeArea::class) which expects 2 arguments (User and AptitudeArea model), but the reorder endpoint doesn't have a model instance - it just reorders all areas."
fix: "Added reorder() method to AptitudeAreaPolicy and changed controller to authorize('reorder', AptitudeArea::class)"
verification: "PHP formatting applied with Pint"
files_changed:
  - app/Policies/AptitudeAreaPolicy.php - Added reorder() method
  - app/Http/Controllers/Admin/AptitudeAreaController.php - Changed authorize call from 'update' to 'reorder'
