# Test guidelines

## How tests run

```bash
make test                              # full suite
make test-filter FILTER=CampaignTest   # one class or method
```

`phpunit.xml` pins `DB_CONNECTION=sqlite` / `DB_DATABASE=:memory:`,
`CACHE_STORE=array`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=array`. The suite must
keep running with no MySQL, no Redis, no network, and no LLM credentials. A test
that needs any of those is not a test we write.

## What to test

| Layer | Test | Why |
|---|---|---|
| Service | Unit, repository contract mocked | Business rules are the thing worth protecting |
| Repository | Feature, `RefreshDatabase`, real sqlite | Queries break silently; assertions on rows catch it |
| API client | Unit, Guzzle `MockHandler` | Status/JSON handling and the exception context it produces |
| Controller / route | Feature, `getJson`/`postJson` | Status codes, validation, authorisation, response shape |
| FormRequest | Feature, through the route | Validation rules and `prepareForValidation()` transforms |
| Vue | Not covered yet | Add Vitest before writing frontend logic worth testing |

Skip tests for framework behaviour, plain getters, and Eloquent relations with no
custom logic.

## Shape of a test

- One behaviour per test method. If the name needs "and", split it.
- Name it as a sentence: `test_rejects_a_brief_without_a_campaign()`.
- Arrange with factories, not hand-built arrays. Every model gets a factory.
- Assert on the outcome the caller cares about — status code plus the specific
  keys that matter, not `assertJsonStructure` over the whole payload.
- Freeze time with `travelTo()` when a date is part of the assertion.

## Never do this

- **Never weaken a test to make it pass.** A failing test is a finding. Change
  the code, or bring the failure to the user — do not relax the assertion.
- **Never assert on log output** to prove behaviour.
- **Never let a test depend on another test's leftovers.** Use `RefreshDatabase`
  and keep each test self-contained.
- **Never call a real external API.** Fake it (`Http::fake()`, a stubbed client
  bound in the container). An LLM call in the suite is an outage waiting to bill
  us.
- **Never add `sleep()`.** If timing matters, inject a clock.

## Testing an external API client

Inject a Guzzle client built on a `MockHandler` — never the container's
singleton, never the network. Assert on three things: the decoded return value,
the `ApiCallFailedException` status, and its `context` (the response body is
there; the raw provider string must NOT be in the message).

`tests/Unit/Core/ApiClientAbstractTest.php` is the reference.

## Fixtures for LLM work

Responses from Claude are recorded once and stored under
`tests/Fixtures/llm/`. Tests bind a fake client that replays the fixture. When
the prompt changes, re-record the fixture deliberately and say so in the spec —
never let the suite start hitting the live API to "fix" a mismatch.
