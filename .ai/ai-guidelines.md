# AI guidelines — index of binding standards

Every agent working in this repo follows these. When a rule here conflicts with
a habit, the rule wins.

## The map

| Document | Covers |
|---|---|
| [`.ai/ARCHITECTURE.md`](./ARCHITECTURE.md) (mirror of [`docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md)) | Structure, layering, module skeleton, configuration |
| [`.ai/test-guidelines.md`](./test-guidelines.md) | How tests are written and run |
| [`../guidelines/backend_guidelines.md`](../guidelines/backend_guidelines.md) | Why the backend is shaped this way + new-module checklist |
| `marketing-backend-ddd` skill | Code templates for every backend layer |
| [`../CLAUDE.md`](../CLAUDE.md) | Spec-driven workflow, phases, agent roles |
| [`../guidelines/DEPLOYMENT-GUIDE.md`](../guidelines/DEPLOYMENT-GUIDE.md) | Server setup and deployment |

## The backend is modular, always

Every backend class lives in `app/Modules/{Module}/`. There is no "small enough
to put in `app/Http/`" — the first exception is the one every later exception
cites. Invoke the `marketing-backend-ddd` skill before writing backend code; it
carries the skeleton and the templates.

Three rules that get broken most often:

- **Repositories return Collections**, never arrays.
- **`try`/`catch` only in repositories and API clients**, which attach context to
  a domain exception and throw it.
- **Never call `Log::` by hand.** The handler logs each `ApiException` once, at
  the level the exception decides. A manual log plus a throw is the same failure
  recorded twice, in two shapes.

## Comments are a last resort

Write a comment only for a **WHY that cannot be expressed in code**: a
non-obvious constraint, a workaround for an external bug, a decision whose
alternative looks better but is wrong.

Never narrate WHAT the code does. If a reader needs a comment to follow the
flow, rename the thing or extract the function instead.

```php
// Bad — narrates the obvious
// Get the user's active campaigns
$campaigns = $this->repository->activeFor($user);

// Good — records a WHY the code cannot show
// The provider rejects batches over 50; larger briefs are split upstream.
$chunks = $briefs->chunk(50);
```

The same applies to docblocks: add one only when it carries type information
PHP cannot express (`@return Collection<int, Campaign>`), never to restate the
signature.

## No single-use variables

Do not assign a value just to pass it on the next line. Inline it.

```php
// Bad
$dto = $request->toDTO();
return $this->service->create($dto);

// Good
return $this->service->create($request->toDTO());
```

The exception is a name that genuinely explains an opaque expression — that is
documentation, not a temporary.

## Match the surrounding code

Naming, ordering, import style, test shape: read the neighbours first. A change
that is individually defensible but locally foreign costs the next reader more
than it saves.

## Style is enforced, not debated

`make pint-fix` before you hand work off. `make pint` must pass. Nobody argues
about brace placement in review.

## Explicit over clever

- No facades inside Services when a constructor-injected dependency will do.
- No `env()` outside `config/` — always `config('...')`, because config is cached
  in production and `env()` returns `null` there.
- No `new Client()` for an outbound call — go through `GuzzleClientFactory`, so
  one change to the timeout policy reaches every integration.
- No silent `catch` blocks. If you swallow an exception, the comment explaining
  why is one of the rare mandatory ones.

## Security defaults

- Every endpoint states its authorisation, even if that statement is "public".
- Never interpolate request data into a raw query. Bind it.
- Never log a token, password, API key, or full request body.
- New third-party credentials go in `src/.env` and are referenced through
  `config/services.php`.

## Feedback becomes a rule, immediately

A correction from the user is recorded in the same turn: a `feedback` entry in
the project's memory directory **and** the standard it belongs to. Writing memory
is the main thread's job — a subagent's report does not persist. Details and the
trigger list: the *Feedback is permanent* section of [`../CLAUDE.md`](../CLAUDE.md).

## When a standard is wrong

Say so and propose the change to the document, in the same delivery. Do not
quietly work around a rule — an unwritten exception is how a standard rots.
