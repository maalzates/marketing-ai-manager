# Test guidelines

## Feature tests only. There is no `tests/Unit`.

Every test in this project enters through a real entry point and exercises the
whole chain behind it:

```
HTTP request → route → controller → service → repository → MySQL
job dispatched → handler → service → repository → MySQL
artisan command → service → repository → MySQL
```

`tests/Unit/` does not exist and is not recreated. `phpunit.xml` declares one
suite, `Feature`.

**Why.** A unit test with the repository mocked asserts that a Service called a
method. It stays green with a broken query, an unregistered route, and a missing
binding in the service provider — the three things that actually break. The
question worth answering is "does the flow work", and only a test that comes in
through the front door answers it.

If the entry point does not exist yet, create it as part of the change. A feature
with no way in is not finished.

## How the suite runs

```bash
make test                       # full suite
make test FILTER=CampaignTest   # one class or method
```

**Against MySQL, not sqlite.** `phpunit.xml` overrides only `DB_DATABASE`
(`marketing_ai_testing`); the host, user and password come from `.env`, so the
suite talks to the same MySQL 8.4 the application talks to. The schema is created
by `docker/mysql/01-create-testing-database.sql` when the volume is first built,
and `make test` replays that same SQL first, so it also exists on volumes that
predate it.

This costs a couple of seconds per run and buys the thing repository tests exist
for: JSON columns, `ENUM`, collations, strict mode and `ONLY_FULL_GROUP_BY`
behave as they will in production. `tests/Feature/Core/MigrationsTest.php` fails
the moment someone points the suite back at sqlite.

Everything else stays out of the way: `CACHE_STORE=array`, `QUEUE_CONNECTION=sync`,
`MAIL_MAILER=array`, `SESSION_DRIVER=array`. No Redis, no network, no LLM
credentials.

Use `RefreshDatabase` in any test that touches the database.

## What to test

| Entry point | Test |
|---|---|
| API endpoint | `getJson`/`postJson` the route. Assert status, the `result` payload, and the rows written. |
| Validation | Post the bad payload through the route; assert `errors.fields`. |
| Authorisation | Hit the route as the wrong user; assert 403 — not by unit-testing a policy. |
| Queued job | Dispatch it; assert the side effect. `QUEUE_CONNECTION=sync` runs it inline. |
| Artisan command | `$this->artisan('...')->assertExitCode(0)`; assert the side effect. |
| External API client | Bind it with a Guzzle `MockHandler`, call it through a route. See `tests/Feature/Core/ExternalApiClientTest.php`. |

Skip tests for framework behaviour, plain getters, and Eloquent relations with no
custom logic.

## Shape of a test

- One behaviour per test method. If the name needs "and", split it.
- Name it as a sentence: `test_rejects_a_brief_without_a_campaign()`.
- Arrange with factories, not hand-built arrays. Every model gets a factory.
- Assert on the outcome the caller cares about — status plus the specific keys
  that matter, not `assertJsonStructure` over the whole payload — and on the row
  in the database when the endpoint writes one.
- Freeze time with `travelTo()` when a date is part of the assertion.

## What may be faked

Only what leaves the machine:

- External HTTP — Guzzle `MockHandler` bound in the container, or `Http::fake()`.
- The LLM — a stubbed client replaying a fixture.
- Time, randomness, the filesystem when it is genuinely awkward.

**Never mock a repository, a Service, or anything else this repo owns.** Mocking
the thing under test is how a green suite hides a broken feature.

## Testing an external API client

Bind the client with a Guzzle `MockHandler`, then call it through a route so the
failure path goes all the way to the error envelope. Assert three things: the
decoded success value, the status the domain exception produces, and the logged
`context` — the provider's raw body belongs there and must NOT appear in the
response.

`tests/Feature/Core/ExternalApiClientTest.php` is the reference.

## Never do this

- **Never write a unit test.** See the top of this file.
- **Never weaken a test to make it pass.** A failing test is a finding. Change
  the code, or bring the failure to the user — do not relax the assertion.
- **Never assert on log output** to prove business behaviour. Logs are fair game
  only when the log line *is* the behaviour (an exception's level and context).
- **Never let a test depend on another test's leftovers.** `RefreshDatabase`, and
  each test self-contained.
- **Never call a real external API.** An LLM call in the suite is an outage
  waiting to bill us.
- **Never add `sleep()`.** If timing matters, inject a clock.

## Fixtures for LLM work

Responses from Claude are recorded once and stored under
`tests/Fixtures/llm/`. Tests bind a fake client that replays the fixture. When
the prompt changes, re-record the fixture deliberately and say so in the spec —
never let the suite start hitting the live API to "fix" a mismatch.
