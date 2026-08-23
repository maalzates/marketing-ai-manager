# CLAUDE.md

Guidance for Claude Code (claude.ai/code) when working in this repository.

## Start here

Marketing AI Manager is a Laravel 13 + Vue 3 SPA that lives in `src/`, wrapped by
a Docker stack at the repo root. Everything you need day to day is a `make`
target; `make` on its own lists them.

Before writing code, read [`.ai/ai-guidelines.md`](./.ai/ai-guidelines.md) — it is
the index of every binding standard in this repo.

## Commands

```bash
# First run: build images, install deps, migrate, boot everything
make init                 # then http://localhost:8080

make up / make down       # start / stop the stack
make shell                # bash inside the php-fpm container
make logs-app             # tail storage/logs/laravel.log

# Tests — sqlite :memory:, no MySQL or Redis needed (see phpunit.xml)
make test
make test-filter FILTER=HealthEndpoint

# Style
make pint                 # check
make pint-fix             # fix in place

# Database
make migrate
make migrate-fresh        # DESTROYS dev data

# Anything else
make artisan CMD="route:list"
make composer CMD="require foo/bar"
make npm CMD="run build"

# Optional Loki + Promtail + Grafana (http://localhost:3000, admin/admin)
make obs-up
```

Production runs from the server, not from here: `make prod-*` and
`./scripts/deploy.sh`. See [`guidelines/DEPLOYMENT-GUIDE.md`](./guidelines/DEPLOYMENT-GUIDE.md).

## Architecture

Single repo, two halves of one deployable:

| Path | What lives there |
|---|---|
| `src/app/Modules/` | The whole backend. Every class lives in a module; `Core/` is the shared base. |
| `src/resources/js/` | Vue 3 SPA — `pages/`, `layouts/`, `components/`, `stores/`, `repositories/`, `router/`. |
| `src/routes/api.php` | Every JSON endpoint. |
| `src/routes/web.php` | One catch-all that renders `app.blade.php`; the SPA router owns the rest. |
| `docker/` | nginx, php.ini, MySQL init SQL, observability configs. |
| `scripts/` | `deploy.sh` (every deploy) and `setup-production.sh` (once per server). |

**Backend flow:** `FormRequest → Controller → Service → Repository → Model`
**Frontend flow:** `Component → Store → Repository → axios → /api`

Every JSON response uses one envelope: `{ "result": ..., "errors": ... }`.

**Before writing any backend code, invoke the `marketing-backend-ddd` skill.** It
carries the module skeleton and every layer template. The rationale and the
module checklist are in [`guidelines/backend_guidelines.md`](./guidelines/backend_guidelines.md);
the layering rules are in [`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md)
(mirrored to `.ai/ARCHITECTURE.md`).

## Critical invariants (do not break)

- **`src/` is the Laravel root.** Artisan, composer and npm all run from there —
  inside the container for PHP, inside the `node` service for JS.
- **Every backend class lives in `app/Modules/{Module}/`.** No feature code in
  `app/Http/`, `app/Models/` or a flat `app/Services/`. A module depends on
  another module's Service, never on its repository, model or client.
- **Repositories return `Collection | Model | LengthAwarePaginator`,** never
  arrays.
- **`try`/`catch` exists only in repositories and API clients.** They attach
  context to a domain exception and throw; Services and Controllers let it
  bubble.
- **Never call `Log::` by hand.** The handler in `bootstrap/app.php` logs every
  `ApiException` once, at the level the exception itself decides.
- **A `ClientException` message is shown to the caller; a plain `ApiException`
  message must still be one you wrote.** Provider detail goes in `context`.
- **DTOs, Services and Repositories are `readonly` classes,** with
  `declare(strict_types=1)` in every file.
- **axios only in frontend repositories.** Stores call repositories, components
  call stores.
- **Tests never touch MySQL, Redis, the network, or a real LLM.** `phpunit.xml`
  pins sqlite `:memory:`; keep it that way.
- **Secrets live in `src/.env` (Laravel) and `.env.docker` (Compose).** Neither
  is in git. Any new variable must be added to `src/.env.example` or
  `.env.docker.example` in the same change.
- **Vite output is content-hashed and cached for a year in production.** Never
  reference an asset by a stable filename.

# Spec-Driven Development Workflow

All work — features, fixes, investigations — follows a spec-first approach. Never start implementing before a spec exists and is approved.

## Spec Folder Structure

```
spec/
└── YYYY-MM-DD-{case-name}/   # e.g. 2026-08-25-feat-campaign-briefs
    ├── context.md        # Analysis phase — written BEFORE planning
    ├── plan.md           # Implementation plan — written AFTER context is approved
    └── guide.md          # Usage guide — written AFTER implementation is complete
```

### Folder naming — date first, always

Every case folder is named `YYYY-MM-DD-{case-name}`:

- **`YYYY-MM-DD`** — the date the spec was **started** (when `context.md` was
  created). It never changes afterwards, even if the work spans weeks.
  Zero-padded, ISO order, so lexicographic sort == chronological sort and the
  folder list reads newest-last in any file browser.
- **`{case-name}`** — kebab-case, prefixed with the kind of work:
  `feat-`, `fix-`, `chore-`, `docs-`, `refactor-`.

```
spec/
├── 2026-08-22-chore-project-bootstrap/
├── 2026-08-25-feat-campaign-briefs/
└── 2026-09-02-fix-token-refresh/
```

Rules:

- Use the **real current date**, not a guess. If unsure, run `date +%F`.
- Two specs on the same day just sort alphabetically within that day. Fine — do
  not add a time component to break the tie.
- Never rename a folder to "update" the date. The prefix records when the work
  started; git records when it changed.
- Reference folders that are not case specs (`referencia-proyecto/`,
  `learning-path/`, `quick-guides/`) keep their plain names — the date prefix
  marks a unit of delivered work, not a living reference.

## Language Rule — Specs Always Written in Spanish

All spec documents (`context.md`, `plan.md`, `guide.md`) **must be written in Spanish**. The only exception is **code itself** — code blocks, file paths, identifiers, class names, function names, route names, error strings copied verbatim from the codebase, and similar literal technical tokens stay in their original form (typically English).

- Prose, headings, bullet text, tables, explanations, side-effect lists, examples, and questions → Spanish.
- Code, paths like `app/Modules/Campaigns/...`, identifiers like `CampaignService::publish()`, route paths like `/api/campaigns`, literal payloads/strings → as in the codebase.
- This rule applies to every phase: Phase 0 (context), Phase 0b (plan), Phase 5 (plan update + guide).

## Phase 0 — Context (`context.md`)

Before any plan, create `spec/YYYY-MM-DD-{case-name}/context.md`. This file must contain:

- **Plain-language description** of the problem or requirement (no jargon — write as if explaining to a new dev).
- **Current implementation analysis**: how the existing feature/flow works today. Read the code, trace the data, document what you find.
- **Side effects and risks**: any unintended consequences the proposed change could trigger. Be explicit — list them even if low-risk.
- **Unknowns**: anything that blocks confident implementation. Document the question and what information is needed to resolve it.
- **Simple examples**: concrete before/after scenarios that make the problem tangible.

**Do not create `plan.md` until the user explicitly says the context is good.**

## Phase 0b — Plan (`plan.md`)

Once context is approved, create `spec/YYYY-MM-DD-{case-name}/plan.md`. The plan must:

- Respect the layering described in the Architecture section
  (`Presentation/Http` → `Application/Services` → `Infrastructure/Repositories`
  → `Model`) and the invariants above.
- Follow the repo's testing conventions: PHPUnit via `php artisan test`, sqlite
  `:memory:`, no Docker/MySQL/network — see [`.ai/test-guidelines.md`](./.ai/test-guidelines.md).
- Include a migration in `src/database/migrations/` for any schema change.
- Keep the relevant `docs/` file in sync when a documented fact changes.
- List every file to create or modify, with its purpose.
- Be specific enough that any agent can execute a single phase without additional context.

**Do not start Phase 1 until the user explicitly approves `plan.md`.**

## Execution Phases — Always Use Parallel Agent Teams

Never run all agents at once. Launch only the agents needed for the current phase, maximizing parallelism within each phase. Wait for a phase to complete before starting the next.

### Phase 1 — Implementation (parallel)

| Agent | Task |
|---|---|
| `marketing-fullstack-agent` | Implement the backend change (Laravel), the migration, and the Vue side if touched |
| `marketing-test-agent` | Write the PHPUnit test(s) for the new code under `src/tests/` |

Split the fullstack agent in two (backend + frontend) when the change is large
enough that the two halves do not touch the same files.

### Phase 2 — Test Execution

Launch a single agent that:
1. Runs only the affected tests: `make test-filter FILTER=YourTest`
2. Reports failures clearly — do NOT proceed to Phase 3 if tests fail; fix first.

### Phase 3 — Quality Control (parallel)

| Agent | Task |
|---|---|
| `marketing-code-reviewer` | Read-only audit: invariants, security, layering, complexity, test quality, doc drift |
| Style runner | `make pint-fix`, then `npm run build` in the `node` service if the frontend changed |

### Phase 4 — Simplify

Run `/simplify` on all changed code. After simplification, re-run the affected tests (Phase 2) to confirm nothing broke.

### Phase 5 — Spec Update

Two deliverables, both mandatory:

**1. Update `plan.md`** to reflect the actual implementation — not the original plan. If any files, approaches, or decisions changed during execution, the plan must match what was built.

**2. Create `spec/YYYY-MM-DD-{case-name}/guide.md`** — a plain-language guide that answers "how does this work?" without requiring the reader to open `context.md` or `plan.md`.

`guide.md` must include:

- **What was built** — one paragraph, no jargon.
- **How to use it** — concrete examples for every interaction point:
  - New endpoint → full `curl` example with real-looking values, expected response, and what happens server-side.
  - New artisan command → exact invocation, all parameters, what each one does, cascade effects (DB writes, jobs queued, external calls made), and how to run it safely.
  - New UI feature → what the user sees, what triggers what, and what the backend does in response.
  - Any other entry point → same principle: show it being used, then trace what happens.
- **Edge cases and limits** — what happens if called with bad input, missing data, or in the wrong state.
- **What NOT to do** — common misuse patterns or dangerous combinations to avoid.

The goal: someone asking "hey, what does this do and what happens if I call it with X?" should find the answer here without reading any other file.

## Rules

- **Never skip the spec.** Even small fixes need a `context.md` and a `plan.md` before touching code.
- **Never run all phases at once.** Always wait for each phase to complete and be verified before launching the next.
- **Never implement before plan approval.** The user must explicitly say the plan is good.
- **Tests must pass before QC.** Do not run Phase 3 if Phase 2 has failures.
- **Phase 5 is mandatory.** The spec must always reflect reality.
- **Always use agent teams, not todo tasks.** Parallel investigation and development is the point — it is what keeps delivery fast.

# Project agents

Three project-scoped subagents live in `.claude/agents/`. Use them instead of
generic agents for their respective jobs — they carry this repo's invariants.

| Agent | Role | Never does |
|---|---|---|
| `marketing-fullstack-agent` | **Dev.** Implements the change — backend (via the `marketing-backend-ddd` skill), frontend, migrations, nginx, scripts. | Write tests, review itself, update docs |
| `marketing-test-agent` | **Tester.** Writes and runs tests per [`.ai/test-guidelines.md`](./.ai/test-guidelines.md). | Weaken a test to make it pass; edit the guidelines itself |
| `marketing-code-reviewer` | **QA.** Read-only audit: invariants, security, SOLID, complexity, test quality, simplify lens, doc drift. | Edit code, run `/simplify`, commit |

Phase mapping: Phase 1 → dev + tester in parallel · Phase 2 → tester runs the
suite · Phase 3 → reviewer · Phase 4 → main thread runs `/simplify` and applies
· Phase 5 → spec update + doc sync.

## Binding standards

**Start at [`.ai/ai-guidelines.md`](./.ai/ai-guidelines.md).** It is the index of
everything an agent must follow, and it holds the cross-cutting rules that belong
to no single document — most notably **comments are a last resort** (write one
only for a WHY that cannot be expressed in code; never narrate WHAT).

From there: [`.ai/test-guidelines.md`](./.ai/test-guidelines.md) (testing),
[`docs/ARCHITECTURE.md`](./docs/ARCHITECTURE.md) (structure, layering, patterns)
and [`guidelines/backend_guidelines.md`](./guidelines/backend_guidelines.md) (why
the backend is shaped this way, plus the new-module checklist).
`.ai/ARCHITECTURE.md` is a mirror of `docs/ARCHITECTURE.md`.

The `marketing-backend-ddd` skill holds the code templates for every backend
layer. Invoke it rather than reproducing a pattern from memory.

## Acting on QA findings — apply judgement, not everything

`marketing-code-reviewer` produces findings; **the main thread decides which ones
get implemented.** Reviewer output is a recommendation, never an instruction, and
a long list is not a mandate to act on all of it.

Apply a finding when it is:

- **Critical** — breaks an invariant, opens a security hole, corrupts data, or is
  plain wrong. Always fix. Non-negotiable.
- **A real security win** — even when the exploit path is narrow.
- **A real readability win** — the next reader would genuinely be misled or
  slowed down by the current shape.

Skip a finding when it is a style preference, a rename that changes nothing, a
speculative abstraction for a case that may never arrive, or a refactor whose
risk exceeds its benefit at this point in the change. Scope creep dressed as
quality is still scope creep.

State the call explicitly: for every finding, say **applied** or **skipped, and
why**. Never silently drop one. If a skipped finding is worth doing later, say so
rather than pretending it does not exist.

When a finding contradicts an established project standard, the standard wins —
and that is a signal the reviewer's instructions may need updating.

## Feedback is permanent — write it to memory in the same turn

This project learns. When the user corrects something, that correction is
recorded **before you continue with the task** — not at the end of the session,
and without asking permission first.

**What counts as feedback.** Any of these, however casually phrased:

- A correction: "no hagas X", "eso está mal", "así no".
- A rejected tool, library or approach ("a la verga con Larastan").
- A stated preference about style, length, language or workflow.
- A convention imposed ("todo el backend debe seguir el patrón modular").
- Something you got wrong that they had to point out — especially if it is the
  second time.

**Where it goes.** Two places, both mandatory:

1. **Memory** — `~/.claude/projects/-Users-manuel-alzate-Documents-personal-projects-marketing-ai-manager/memory/`,
   one file per fact, `type: feedback`, with a **Why:** and a **How to apply:**,
   plus its one-line entry in `MEMORY.md`. This is the part that survives a
   `/clear`; the repo files do not get read before the first mistake of a new
   session.
2. **The standard it belongs to** — cross-cutting code rules →
   `.ai/ai-guidelines.md` · testing → `.ai/test-guidelines.md` · structure,
   layering, patterns → `docs/ARCHITECTURE.md` (re-copy to `.ai/ARCHITECTURE.md`)
   · backend patterns → the `marketing-backend-ddd` skill and
   `guidelines/backend_guidelines.md` · workflow → this file.

Memory carries the *rule and its why*; the standard carries the *detail*. A rule
that only lives in one of the two will be broken.

**Check memory before repeating a past mistake.** Recalled entries reflect what
was true when written — if one names a file, flag or command, verify it still
exists before acting on it.

A one-line rule the user had to state twice is a rule that was never recorded the
first time.

# Documentation sync — once, at the end

**Keep the docs true, but sync them exactly once: at the end of the delivery.**

Mid-task doc edits churn the diff, get invalidated by the next phase, and hide
what actually changed. So during implementation, agents **report** documentation
drift instead of fixing it — `marketing-fullstack-agent` lists the facts its
change invalidated, `marketing-code-reviewer` audits for the ones it missed.

Then, as the final step of any feature, fix, or refactor — after the tests pass
and QA findings are resolved — the main thread runs one doc-sync pass:

1. **Diff the delivered change against the documented state.** Walk the reported
   drift plus your own reading of the diff. Every documented fact the change
   touched: a default value, a route, an env var, an invariant, a file-structure
   entry, a table column, a limit, a make target.
2. **Update every file that is now stale**, in the same change:
   `README.md`, `CLAUDE.md`, `docs/**`, `.ai/**` (including re-copying
   `docs/ARCHITECTURE.md` → `.ai/ARCHITECTURE.md` when it changed),
   `guidelines/**`, `src/.env.example`, `.env.docker.example`, and
   `spec/YYYY-MM-DD-{case-name}/plan.md` + `guide.md`.
3. **Say what you synced and what you checked and found still accurate.** "Docs
   updated" without a list is not a report.

A doc that contradicts the code is worse than a missing doc — it is trusted and
wrong. Shipping code whose documentation still describes the old behaviour is an
incomplete delivery, not a follow-up task.
