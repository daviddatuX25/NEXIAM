## ADDED Requirements

### Requirement: Fake remains default
The system SHALL bind `FakeAiGateway` when `nexi.ai.gateway` is `fake` or unset.

#### Scenario: Default gateway is fake
- **WHEN** config uses the default gateway
- **THEN** `AiGateway` resolves to `FakeAiGateway`

### Requirement: Laravel adapter isolates SDK
The system SHALL implement a `LaravelAiGateway` that satisfies `AiGateway` without leaking `Laravel\Ai` types to orchestration code.

#### Scenario: Stream yields NEXIAM chunk DTOs
- **WHEN** the Laravel adapter streams under Agent fake
- **THEN** chunks use only `{type, text?, finish_reason?, usage?}` shapes

### Requirement: Missing key degrades closed
#### Scenario: Laravel gateway without API key
- **WHEN** gateway=`laravel` and the configured provider API key is empty
- **THEN** `health().ok` is false / `degraded` is true and generate/stream fail with a normalized provider-unavailable reason (no hang)

### Requirement: No mutation tools in this cut
#### Scenario: Inquiry agent has no tools
- **WHEN** the spike agent is invoked
- **THEN** no domain mutation tool requests are emitted
