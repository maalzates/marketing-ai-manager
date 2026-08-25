# SETUP.md — running Marketing AI Manager locally

From an empty machine to a working application: external consoles, environment
files, bring-up, first login, onboarding. Follow it top to bottom once; after
that `make start` is all you need.

Production deployment is **not** here — it lives in
[`docs/deployment.md`](./docs/deployment.md).

---

## 1. Two kinds of credentials — read this first

Confusing these is the single most likely way to get stuck.

| | **Platform credentials** | **User credentials** |
|---|---|---|
| Identify | *the application* | *a person using it* |
| Configured by | whoever runs the deployment, once | each user, in the app |
| Live in | `src/.env` | the `integrations` table, encrypted per account |
| Examples | Google OAuth client id/secret, Meta App id/secret, the redirect URIs, `META_GRAPH_VERSION`, `ADMIN_EMAILS`, `APP_KEY`, database and Redis settings | Apify token, Anthropic/OpenAI/Gemini API key, the Meta OAuth connection, the Google OAuth connection |
| In git | never (`src/.env` is gitignored; `src/.env.example` is the documented template) | never, in any form |

**BYOK — bring your own keys.** No user credential is ever read from the
environment. There is no fallback key, no shared account key, no "admin key used
when the user has none". Each user pastes their own Apify and LLM keys into the
onboarding wizard and clicks through their own Meta and Google OAuth flows. The
consequence is deliberate and stated in `spec/2026-08-23-initial-app-development/core.md`
§5: the variable cost (scraping and LLM tokens) belongs to the user, and the only
fixed cost of the platform is hosting.

If you find yourself wanting to put an Apify token or an `ANTHROPIC_API_KEY` in
`src/.env`, stop — that is the invariant being broken, not a missing variable.

---

## 2. Prerequisites

- **Docker** and **Docker Compose v2** (`docker compose`, not `docker-compose`).
  Nothing else. No local PHP, Composer, Node or MySQL is required — every one of
  them runs inside a container.
- **GNU Make**, which ships with macOS and every Linux distribution.
- A Google account (you will sign into the app with it), and accounts on the
  provider consoles you intend to use.

### Ports the stack binds

| Port | Service | Override with |
|---|---|---|
| 80 | Application (nginx) | `HTTP_PORT` |
| 5173 | Vite dev server | `VITE_PORT` |
| 3307 | MySQL (host side; 3306 inside the network) | `MYSQL_PORT` |
| 6380 | Redis (host side; 6379 inside the network) | `REDIS_PORT` |
| 3000 | Grafana — only with the `observability` compose profile | `GRAFANA_PORT` |

MySQL and Redis are deliberately shifted off 3306/6379 so the stack does not
collide with a locally installed server. Overrides are read by Docker Compose
from the shell or from a root-level `.env`:

```bash
HTTP_PORT=9000 make up
```

### Two facts about the layout that explain everything else

- **`src/` is the Laravel root.** `artisan`, `composer.json`, `.env`, `routes/`,
  `tests/` and `resources/js/` all live under `src/`. The repository root holds
  the Docker stack that wraps it.
- **Every PHP command runs inside the `app` container.** Use `make artisan
  CMD="..."` or `make exec` for a shell. Running `php artisan` on the host will
  fail or, worse, half-work against the wrong PHP version.

---

## 3. Environment variables

Two files, two different consumers:

| File | Read by | When | Template |
|---|---|---|---|
| `src/.env` | Laravel | always | `src/.env.example` |
| `.env.docker` | Docker Compose, via `--env-file .env.docker` | **production only** — `scripts/deploy.sh` and `scripts/setup-production.sh` pass it explicitly | `.env.docker.example` |

Locally you only need `src/.env`. `make up` copies it from the example if it does
not exist. The container-level values (database name, user, password, ports) come
from the defaults baked into `docker-compose.yml`, which are chosen to match the
defaults in `src/.env.example`.

### 3.1 The deliberate overlap on the database credentials

MySQL is **created** with one set of values and **connected to** with another:

| Direction | Variable | Where |
|---|---|---|
| Creates the schema and the user | `MYSQL_DATABASE`, `MYSQL_USER`, `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` | `docker-compose.yml` environment, defaulted to `marketing_ai` / `marketing_ai` / `secret` / `root`; overridable from the shell or a root `.env`, and set from `.env.docker` in production |
| Logs into that schema | `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `src/.env` |

They must agree. If you change one side and not the other, the application cannot
log in to its own database and every request dies with
`SQLSTATE[HY000] [1045] Access denied`. The MySQL container will *not* re-apply a
changed password to an existing data volume either — see §7.

Left at their defaults, both sides already agree and there is nothing to do.

### 3.2 `src/.env` — every variable

57 variables. Scope column: **P** = platform (set it), **I** = infrastructure
(leave it unless you know why), **U** = would be a user credential (never here).

#### Application

| Variable | What it is | Where it comes from | Example | Scope |
|---|---|---|---|---|
| `APP_NAME` | Display name, also feeds `VITE_APP_NAME` and the mail "from" name | you | `"Marketing AI Manager"` | P |
| `APP_ENV` | Environment name | fixed for local | `local` | I |
| `APP_KEY` | Laravel's encryption key. **Encrypts every stored user credential.** Empty until generated | `make up` runs `php artisan key:generate` when it is missing | `base64:Yx3f…=` | P |
| `APP_DEBUG` | Full stack traces in responses | `true` locally, never in production | `true` | I |
| `APP_URL` | Base URL. **`route()` builds the OAuth callback URIs from this**, so it must match the port you actually browse | you | `http://localhost` | P |
| `APP_LOCALE` | Default locale | you | `en` | I |
| `APP_FALLBACK_LOCALE` | Locale used when a translation is missing | you | `en` | I |
| `APP_FAKER_LOCALE` | Locale for factory data | you | `en_US` | I |
| `APP_MAINTENANCE_DRIVER` | Where maintenance mode is stored | fixed | `file` | I |
| `BCRYPT_ROUNDS` | Hashing cost. Used for API-key hashing; **there are no user passwords in this system** | fixed | `12` | I |

#### Logging

| Variable | What it is | Example | Scope |
|---|---|---|---|
| `LOG_CHANNEL` | Log stack in use | `stack` | I |
| `LOG_STACK` | Channels inside the stack | `single` | I |
| `LOG_DEPRECATIONS_CHANNEL` | Where PHP deprecations go | `null` | I |
| `LOG_LEVEL` | Minimum level written | `debug` | I |

#### Database

| Variable | What it is | Where it comes from | Example | Scope |
|---|---|---|---|---|
| `DB_CONNECTION` | Driver | fixed | `mysql` | I |
| `DB_HOST` | Compose service name, **not** `127.0.0.1` — the app talks to MySQL over the Docker network | fixed | `db` | I |
| `DB_PORT` | Port *inside* the network. The 3307 you use from the host is the published port, not this one | fixed | `3306` | I |
| `DB_DATABASE` | Schema | must equal `MYSQL_DATABASE` | `marketing_ai` | P |
| `DB_USERNAME` | User | must equal `MYSQL_USER` | `marketing_ai` | P |
| `DB_PASSWORD` | Password | must equal `MYSQL_PASSWORD` | `secret` | P |

The test suite runs against a **separate schema**, `marketing_ai_testing`, created
by `docker/mysql/01-create-testing-database.sql`. `make up` and `make test` both
apply that file, so it exists even on a volume created before the file did.

#### Session, cache, queue, Redis

| Variable | What it is | Example | Scope |
|---|---|---|---|
| `SESSION_DRIVER` | Session store | `redis` | I |
| `SESSION_LIFETIME` | Minutes | `120` | I |
| `SESSION_ENCRYPT` | Encrypt session payloads | `false` | I |
| `SESSION_PATH` | Cookie path | `/` | I |
| `SESSION_DOMAIN` | Cookie domain | `null` | I |
| `BROADCAST_CONNECTION` | Broadcast driver | `log` | I |
| `FILESYSTEM_DISK` | Default disk. Assets live in the user's Google Drive, not here | `local` | I |
| `QUEUE_CONNECTION` | Queue driver. The `queue` container runs `queue:work` against it | `redis` | I |
| `CACHE_STORE` | Cache driver — also backs the settings registry cache | `redis` | I |
| `MEMCACHED_HOST` | Unused; Laravel default | `127.0.0.1` | I |
| `REDIS_CLIENT` | PHP extension used | `phpredis` | I |
| `REDIS_HOST` | Compose service name | `redis` | I |
| `REDIS_PASSWORD` | No auth on the dev container | `null` | I |
| `REDIS_PORT` | Port inside the network | `6379` | I |

#### Mail

The application sends no mail: there is no `Mailable`, no `Mail::` call and no
notification channel wired up. These variables exist only because
`config/mail.php` always reads them, and `MAIL_MAILER=log` keeps that harmless —
anything a future feature sends lands in `storage/logs/laravel.log` instead of
leaving the machine. There is no local SMTP catcher in the stack.

| Variable | Example | Scope |
|---|---|---|
| `MAIL_MAILER` | `log` | I |
| `MAIL_SCHEME` | `null` | I |
| `MAIL_HOST` | `127.0.0.1` — unused while `MAIL_MAILER=log` | I |
| `MAIL_PORT` | `2525` | I |
| `MAIL_USERNAME` | `null` | I |
| `MAIL_PASSWORD` | `null` | I |
| `MAIL_FROM_ADDRESS` | `"hello@example.com"` | I |
| `MAIL_FROM_NAME` | `"${APP_NAME}"` | I |

#### AWS

Laravel scaffolding. Unused by this application — no S3 disk is configured, and
assets are stored in the user's Drive.

| Variable | Example | Scope |
|---|---|---|
| `AWS_ACCESS_KEY_ID` | *(empty)* | I |
| `AWS_SECRET_ACCESS_KEY` | *(empty)* | I |
| `AWS_DEFAULT_REGION` | `us-east-1` | I |
| `AWS_BUCKET` | *(empty)* | I |
| `AWS_USE_PATH_STYLE_ENDPOINT` | `false` | I |

#### Frontend

| Variable | What it is | Example | Scope |
|---|---|---|---|
| `VITE_APP_NAME` | Name exposed to the SPA | `"${APP_NAME}"` | I |
| `VITE_DEV_SERVER_URL` | Where the browser reaches Vite. nginx proxies the dev server, so this is the app's own origin with no extra port — and `5173` is deliberately not published | `http://localhost` | I |

#### Platform credentials — the ones you actually have to fill in

| Variable | What it is | Where it comes from | Example | Scope |
|---|---|---|---|---|
| `ADMIN_EMAILS` | Comma-separated emails that receive the `admin` role **on their first Google login**. Everyone else gets `user`. Read by `config/accounts.php` | you | `maalzates@gmail.com` (the default) or `you@example.com,other@example.com` | P |
| `GOOGLE_CLIENT_ID` | OAuth 2.0 client id | Google Cloud Console → §4.1 | `1234987819200.apps.googleusercontent.com` | P |
| `GOOGLE_CLIENT_SECRET` | OAuth 2.0 client secret | same client | `GOCSPX-…` | P |
| `META_APP_ID` | Meta app id | developers.facebook.com → §4.2 | `1234567890123456` | P |
| `META_APP_SECRET` | Meta app secret | same app | 32 hex characters | P |
| `META_REDIRECT_URI` | Meta OAuth callback | you | `http://localhost/api/v1/integrations/meta/oauth/callback` | P |
| `META_GRAPH_VERSION` | Graph API version the app targets. Pinned in one place on purpose | fixed unless you are migrating | `v26.0` | P |

**A redirect-URI subtlety that will cost you an afternoon.** The integrations
OAuth flow derives its callback URI from the named route rather than from the
environment variable (`IntegrationOAuthService::redirectUri()`), because the value
must be byte-identical in the authorisation request and in the code exchange.
`route()` builds it from `APP_URL`. So:

- `APP_URL` must be exactly the origin you browse (`http://localhost`, or with the
  port if you overrode `HTTP_PORT`).
- **Google needs more than one redirect URI registered**: the sign-in callback
  *and* the Drive integration callback, plus the YouTube one if you enable it.
  See §4.1 step 9 for the full list.

The base URLs, scope lists, actor ids and model prices are **not** environment
variables — they are in `src/config/services.php`, under version control, because
they are facts about the providers rather than secrets.

### 3.3 `.env.docker` — production only

Seven variables, none of which you need locally. Listed for completeness; the
file is created and mostly auto-filled by `scripts/setup-production.sh`.

| Variable | What it is | Where it comes from | Example | Scope |
|---|---|---|---|---|
| `APP_DOMAIN` | Public domain nginx serves by name, and the `Host` header `deploy.sh` uses for its health check. No certificate is selected: TLS terminates at Cloudflare | you | `marketing.example.com` | P |
| `MYSQL_DATABASE` | Schema the container creates | you | `marketing_ai_production` | P |
| `MYSQL_USER` | User the container creates — must equal `DB_USERNAME` in `src/.env` | you | `marketing_ai` | P |
| `MYSQL_PASSWORD` | Its password — must equal `DB_PASSWORD` | `openssl rand -base64 32` | *(random)* | P |
| `MYSQL_ROOT_PASSWORD` | MySQL root password | `openssl rand -base64 32` | *(random)* | P |
| `GRAFANA_ADMIN_USER` | Grafana login, `observability` profile only | you | `admin` | P |
| `GRAFANA_ADMIN_PASSWORD` | Grafana password | `openssl rand -base64 32` | *(random)* | P |

### 3.4 Compose-level variables (not in either example file)

Read by `docker-compose.yml` from the shell or a root `.env`. All have defaults.

| Variable | Default | Purpose |
|---|---|---|
| `HTTP_PORT` · `VITE_PORT` · `MYSQL_PORT` · `REDIS_PORT` · `GRAFANA_PORT` | 80 · 5173 · 3307 · 6380 · 3000 | Published host ports |
| `UID` · `GID` | your own, exported by the Makefile | Makes `php-fpm` run as you so bind-mounted files stay writable from both sides |

---

## 4. Provider consoles

Every path below is the one recorded in `spec/2026-08-23-initial-app-development/research/`
and in the onboarding guides seeded into the database. Consoles get redesigned —
if a menu has moved, fix the seeded guide (`OnboardingGuideSeeder`, editable from
the admin panel without a deploy) as well as this file.

### 4.1 Google — Cloud Console

One OAuth client covers three things: signing into the application, Google Drive
for the asset library, and YouTube Data if you enable it.

> ## ⚠️ Publish the consent screen to **Production**. Do not leave it in Testing.
>
> Google, verbatim
> ([developers.google.com/identity/protocols/oauth2](https://developers.google.com/identity/protocols/oauth2)):
>
> > "A Google Cloud Platform project with an OAuth consent screen configured for
> > an external user type and a publishing status of 'Testing' is issued a refresh
> > token expiring in 7 days, unless the only OAuth scopes requested are a subset
> > of name, email address, and user profile."
>
> And ([support.google.com/cloud/answer/15549945](https://support.google.com/cloud/answer/15549945)):
>
> > "Authorizations by a test user will expire seven days from the time of
> > consent. If your OAuth client requests an `offline` access type and receives a
> > refresh token, that token will also expire."
>
> This application requests `drive.file`, which is **outside** that
> name/email/profile subset. So in Testing, **every user's Drive connection dies
> exactly seven days after they grant it** — background jobs start failing with
> `invalid_grant`, users are asked to reconnect over and over, and nothing in the
> application logs points at the consent screen as the cause. It works perfectly
> for a week and then rots. This is the most expensive mistake an operator can
> make here.
>
> **Where to change it:** Google Cloud Console → **Google Auth Platform** →
> **Audience** (the page formerly called "OAuth consent screen"). It shows the
> **Publishing status** and carries the **Publish app** button, which
> "transitions a project to in-production status".
>
> **Does that require verification? No.** You can publish without it. The state is
> called *Published (Unverified)*: any Google user can access, your app's name and
> logo are not shown on the consent screen, and for **sensitive or restricted**
> scopes users see a warning screen and a hard cap of 100 total users applies.
> `drive.file` is officially **non-sensitive** and `openid`/`email`/`profile` are
> basic, so the core scope set triggers neither. The YouTube scopes are the ones
> that would — see the note in step 6.

**Steps**

1. Go to <https://console.cloud.google.com> and create a project, or select one.
2. Enable the APIs you need — **APIs & Services → Library**:
   - **Google Drive API** — required for the asset library (§13 of `core.md`).
   - **YouTube Data API v3** — optional; only if you will analyse YouTube.
3. Note the YouTube quota if you enabled it: as of 2026-06-01 it is bucketed —
   100 `search.list` calls/day and 100 `videos.insert`/day in their own buckets,
   plus 10,000 units/day for everything else. The buckets are **not**
   interchangeable.
4. Go to **Google Auth Platform → Audience** and configure the consent screen.
   Choose user type **External** and fill in the application details.
5. Add the scopes. The exact strings the application requests
   (`src/config/services.php`, `services.google`):

   | Scope | Purpose | Class |
   |---|---|---|
   | `openid` | OpenID Connect; yields the `id_token` | Basic |
   | `email` | Adds `email` and `email_verified` claims | Basic |
   | `profile` | Adds the default profile claims | Basic |
   | `https://www.googleapis.com/auth/drive.file` | Create and manage **only** the files this app creates or the user explicitly shares with it | Non-sensitive |
   | `https://www.googleapis.com/auth/youtube.readonly` | "View your YouTube account" — optional | see below |

6. **On the YouTube scope:** Google's own documentation does not enumerate the
   YouTube scopes by sensitivity class. It is widely reported that
   `youtube.readonly` is **sensitive** (not restricted) — meaning verification is
   needed to publish without a warning screen, but no third-party security
   assessment. *This is unverified.* The Cloud Console **Data Access** page labels
   each added scope with its class; check it there before committing to anything.
   If you do not need YouTube, leave the scope out and the question disappears.
7. **Press "Publish app"** and confirm **Publishing status** reads
   **In production**. Re-read the warning box above if you are tempted to skip
   this.
8. Create the client — **APIs & Services → Credentials → Create credentials →
   OAuth client ID**, application type **Web application**.
9. Under **Authorized redirect URIs**, add every callback this deployment uses.
   They must match **exactly**, character for character, including the scheme,
   the port and the absence of a trailing slash:

   ```
   http://localhost/api/v1/auth/google/callback
   http://localhost/api/v1/integrations/google/oauth/callback
   http://localhost/api/v1/integrations/youtube/oauth/callback
   ```

   The first is sign-in. It has no environment variable of its own: it is derived
   from `APP_URL` in `config/services.php`, so set `APP_URL` right and the two can
   never drift apart. The second is the Drive
   connection made during onboarding. The third is only needed if you enabled
   YouTube. **Registering only the first is the most common Google setup error.**
10. Copy the **Client ID** and **Client Secret** into `src/.env` as
    `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET`.

**What the app does with the grant.** It authorises with `access_type=offline`
and `prompt=consent` — the documented way to guarantee a refresh token — stores
the refresh token encrypted, and renews access on its own from then on. A
code-exchange response arriving with no `refresh_token` is treated as a hard
error rather than silently overwriting the stored one.

Two more limits worth knowing: Google allows **100 refresh tokens per Google
account per OAuth client id** (issuing a 101st silently invalidates the oldest),
and a refresh token unused for six months expires.

**On `drive.file`:** it grants access only to files this application created or
that the user explicitly handed over via a file picker. The app **cannot list or
search the rest of the user's Drive**. That is deliberate — it is what keeps the
scope non-sensitive. The practical consequence is that the app creates and
remembers its own root folder by id; it cannot "find" a folder the user made by
hand, and if the user deletes that folder the app re-creates it.

Docs: [OAuth 2.0 for web server apps](https://developers.google.com/identity/protocols/oauth2/web-server)
· [Choose Drive API scopes](https://developers.google.com/workspace/drive/api/guides/api-specific-auth)
· [YouTube quota calculator](https://developers.google.com/youtube/v3/determine_quota_cost)

### 4.2 Meta — developers.facebook.com

One Meta app covers the Marketing API (paid campaigns), Instagram content
publishing and Instagram insights, all through **Facebook Login for Business**.

That flavour is forced, not preferred. Meta documents that an app "can either use
Facebook Login or Instagram Login but not both", and the Marketing API's
`ads_management` is only reachable through the Facebook side. Everything targets
host `graph.facebook.com` at version **`v26.0`**, set once via `META_GRAPH_VERSION`
and read from `config('services.meta.graph_version')`.

**Steps**

1. Go to <https://developers.facebook.com/> and sign in.
2. Open the **My Apps** dropdown and click **Create App**.
3. Open **+ Add Product** and add **Marketing API**. Add **Facebook Login** as
   well — that is what makes the connect button work.
4. In **App settings → Basic**, copy the **App ID** and **App Secret** into
   `src/.env` as `META_APP_ID` and `META_APP_SECRET`.
5. In **Facebook Login → Settings**, add this deployment's callback to **Valid
   OAuth Redirect URIs**, exactly:

   ```
   http://localhost/api/v1/integrations/meta/oauth/callback
   ```

6. Add every person who will use the app under **App Roles → Roles**, as
   administrator, developer or tester. See the next section for why this matters.

**Permissions the app requests** (`config('services.meta.scopes')`):

| Scope | What it is for | App Review |
|---|---|---|
| `ads_management` | Create campaigns, ad sets, creatives and ads | Yes |
| `ads_read` | Read ad performance data | Yes |
| `business_management` | Read/claim business-owned ad accounts; also required by `GET /{ig-user-id}/media` when the user's Page role comes from Business Manager | Yes |
| `pages_show_list` | Enumerate the user's Pages | No (dependencies only) |
| `pages_read_engagement` | Read the Page → Instagram linkage | Yes |
| `instagram_basic` | Read the IG business account profile and media | Yes |
| `instagram_manage_insights` | Media and account insights | Yes |
| `instagram_content_publish` | Publish organic Instagram content | Yes |

**Development mode: App Review is not required for you.** Meta documents that
"apps in Development mode can only request permissions from role users, and only
permissions with standard or advanced access levels", and that App Review is not
required while in Development mode. Role users are the app's **administrators,
developers and testers**.

So a dev-mode app *can* drive real ad accounts and real Instagram accounts with
the full permission list above, for anyone holding a role on the app. **App
Review is only the gate for serving people who do not have a role.** For a local
setup, for a team, or for a single-business deployment, you never need it.

Two caveats:

- Data generated in Development mode — test posts and the like — "will become
  visible to all app users once you switch" to Live.
- Separately, the **Marketing API access tier** limits *volume*, not capability.
  You start on **Limited / Development Access** ("heavily rate-limited per ad
  account. For development only"), granted automatically when you add the
  Marketing API product. Moving to Full/Standard requires at least 500 Marketing
  API calls in the last 15 days with an error rate under 15%, then App Review.

**Sandbox ad account (recommended for testing).** A sandbox account accepts every
Marketing API call — create a campaign, set a budget, pause it — but Meta never
delivers the ads, so nothing is ever spent. Creation, verbatim from Meta's blog
post:

1. Navigate to <https://developers.facebook.com/>
2. Click the **My Apps** dropdown
3. Select an app where you have Administrator or Developer Access
4. Choose **Marketing API** (or add it via **+ Add Product**)
5. Under **Marketing API**, select **Tools**
6. Access **Sandbox Mode** from there

Limits, also from the docs: **one sandbox ad account per app**, regardless of
access tier; no funding source needed, "since Facebook will not deliver the ads
you create"; no impressions and no spend accumulate; and sandbox accounts are not
visible in Ads Manager or Power Editor. Because nothing is delivered, **there is
no realistic insights data** — the campaign guardian and the verdict logic are
tested against seeded metrics and recorded HTTP fixtures, not against sandbox
numbers.

Inside the application, sandbox is a per-account checkbox in **Settings → Sandbox
mode** (default off). While it is on, a permanent `SANDBOX` badge shows across the
UI and the mode change is written to the action log.

**Token lifetime, so it does not surprise you later.** Meta's flow is: short-lived
user token → exchanged server-side for a long-lived one (~60 days) → stored
encrypted. **There is no refresh-token grant.** An expired token cannot be
exchanged; the user simply logs in again. The app watches expiry and prompts for
reconnection when fewer than 7 days remain. That prompt is normal, not a fault.

Docs: [Permissions reference](https://developers.facebook.com/docs/permissions/reference)
· [App modes](https://developers.facebook.com/docs/development/build-and-test/app-modes)
· [Long-lived tokens](https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived)
· [Sandbox ad accounts](https://developers.facebook.com/ads/blog/post/v2/2016/10/19/sandbox-ad-accounts/)

### 4.3 Apify — the user's own token

Apify powers competitor analysis: Instagram posts, Instagram comments and the
Meta Ad Library. The token is **per user** and is pasted into the onboarding
wizard — it never goes in `src/.env`.

**Where to get it**

1. Sign in to the **Apify Console** — <https://console.apify.com>
2. Open **Settings** (left sidebar / account menu)
3. Open the **API & Integrations** tab
4. The **Personal API token** is shown there; click the **Copy** icon
5. Direct link: <https://console.apify.com/settings/integrations>

A default token grants full account access. Toggling **"Limit token permissions"**
creates a scoped token. **Recommended scope for this app: "run any Actor" + "read
datasets"** — nothing more. Scoped tokens cannot create or modify Actors, which
this app never does.

**What it costs.** The three Actors are all **pay-per-result**, so result count is
the cost driver and `maxItems` is the budget knob the app uses:

| Purpose | Actor | Price (Free plan → Business plan) |
|---|---|---|
| Instagram profile / posts / reels | `apify~instagram-scraper` | $2.70 → $1.50 per 1,000 results |
| Instagram comments | `apify~instagram-comment-scraper` | ~$2.30 → $1.90 per 1,000 comments |
| Meta / Facebook Ad Library | `apify~facebook-ads-scraper` | $5.80 → $3.40 per 1,000 ads |

**Free tier:** $5 in monthly platform credits, **no credit card required**, up to
16 GB RAM and 25 concurrent runs. Credits do **not** roll over — they expire at
the end of the cycle. At Free-tier pricing that $5 buys roughly **1,850 Instagram
results per month**, which is a real constraint when sizing how many competitors
to track. Paid tiers start at $29/month (Starter) with $29 of credits.

Every run's true cost is read back from `data.usageTotalUsd` and written to the
consumption ledger, so a user can see exactly what their analyses cost.

Docs: [Apify pricing](https://apify.com/pricing) ·
[Actors in Store — pricing models](https://docs.apify.com/platform/actors/running/actors-in-store)

### 4.4 LLM providers — the user's own key

Also per user, also entered in onboarding. **A user configures at least one**; the
application does not ship with a key and will not work around a missing one.

Given a key, the app routes each AI task to a cheap or a capable model according
to the task's declared tier — mechanical work (classifying sentiment, filtering
comments, extracting themes) goes to a small model; judgement work (scripts,
campaign proposals, verdicts, chat) goes to a large one. Every task's model is
overridable in **Settings → Models**, with a "use the same model for everything"
checkbox that collapses the whole thing to a single selector.

| Provider | Where to get a key | Key format |
|---|---|---|
| **Anthropic (Claude)** | <https://platform.claude.com> → **Settings → API keys** → **Create key**. Direct: <https://platform.claude.com/settings/keys> | `sk-ant-api03-…` — the console states it "starts with `sk-ant-`" |
| **OpenAI** | <https://platform.openai.com> → **API keys** → **Create new secret key**. Direct: <https://platform.openai.com/api-keys> | `sk-proj-…` (project key, the normal case) or `sk-svcacct-…` (service account). Legacy `sk-…` keys are being phased out |
| **Google Gemini** | **Google AI Studio** → **Get API key** → **Create API key**. Direct: <https://aistudio.google.com/apikey>. First-time users get a Google Cloud project created automatically | `AIza…` (classic) **or** `AQ.…` (new format, rolling out) — the app accepts both prefixes |

Notes that matter in practice:

- **Anthropic and OpenAI keys are shown once.** Copy immediately; a lost key
  cannot be recovered, only replaced.
- **`sk-admin-` OpenAI keys cannot call models.** They manage projects and
  billing. The app rejects them with a specific message rather than a generic
  "invalid key".
- **Gemini's `AQ.` format is a live, moving situation.** Community reports from
  June–July 2026 say `AQ.` keys are rejected by the
  `generativelanguage.googleapis.com` REST endpoint with
  `401 ACCESS_TOKEN_TYPE_UNSUPPORTED`. *This is unverified* — it comes from the
  Google AI Developers Forum, not official documentation. If validation fails
  that way, generate an `AIza` key instead.
- **Also unverified:** a reported deadline of 2026-06-19 after which Google blocks
  Gemini calls from keys with no API-level restrictions configured. The official
  page could not be retrieved. If a previously working `AIza` key stops
  authenticating, check its API restrictions in the Cloud Console.

The per-provider base URLs, key-prefix checks and model prices live in
`src/config/services.php`. Prices were verified against the providers' own pages
on **2026-08-23** (`llm_prices_verified_at`); they move, so re-check rather than
trusting the number.

---

## 5. Bring-up

```bash
git clone git@github.com:maalzates/marketing-ai-manager.git
cd marketing-ai-manager
make up
```

### What `make up` does, in order

1. Copies `src/.env.example` to `src/.env` **if `src/.env` does not exist**. An
   existing file is never touched.
2. `docker compose build` — builds the PHP 8.4-FPM image with `bcmath`, `exif`,
   `gd`, `intl`, `mbstring`, `opcache`, `pcntl`, `pdo_mysql`, `zip` and `redis`,
   running as your UID/GID so bind-mounted files stay writable.
3. Starts `db` and `redis`, then blocks until `mysqladmin ping` succeeds.
4. Applies `docker/mysql/01-create-testing-database.sql`, creating the
   `marketing_ai_testing` schema and granting it to `marketing_ai`. Idempotent,
   so it also fixes volumes created before that file existed.
5. `composer install`.
6. `docker compose up -d` — brings up `app`, `queue`, `nginx` and `node`
   alongside `db` and `redis`.
7. Generates `APP_KEY` **only if** `src/.env` has no `APP_KEY=base64:` line.
8. `php artisan migrate --force`.
9. `php artisan storage:link` if the symlink is missing.
10. Prints the application URL.

`make up` is safe to re-run: the build is cached, Composer is a no-op on an
unchanged lock file, and migrations are idempotent. Use `make start` for a plain
resume after `make stop`.

### Then seed

**`make up` does not run the seeders.** Run them once:

```bash
make artisan CMD="db:seed"
```

That runs `DatabaseSeeder`, which calls:

| Seeder | What it creates |
|---|---|
| `RoleSeeder` | The `admin` and `user` roles |
| `DomainKnowledgeSeeder` | The Meta Ads domain knowledge base (§11 of `core.md`) |
| `MetricGlossarySeeder` | The metric glossary |
| `OnboardingGuideSeeder` | The six onboarding guides (Anthropic, OpenAI, Gemini, Apify, Meta, Google) as versioned, admin-editable content |

All four use `firstOrCreate`, so re-running is harmless.

**How the admin role is actually granted.** `RoleSeeder` only creates the *roles*.
Nobody is an admin until they log in: on first Google login, `AuthService` checks
the user's email against `config('accounts.admin_emails')` — which reads
`ADMIN_EMAILS` from `src/.env`, defaulting to **`maalzates@gmail.com`** — and
assigns `admin` if it matches, `user` otherwise. Set `ADMIN_EMAILS` to your own
address **before your first login**, or you will sign in as a plain user and have
to fix it in the database.

### Run the tests

```bash
make test                         # everything
make test FILTER=HealthEndpoint   # one test
```

The suite runs inside the container against the `marketing_ai_testing` MySQL
schema. It never touches the development database, the network, or a real LLM.

### Smoke checklist

Everything below must pass before you consider the environment working.

| # | Check | Command | Expected |
|---|---|---|---|
| 1 | The app answers | `curl -s -o /dev/null -w '%{http_code}\n' http://localhost` | `200` |
| 2 | The API envelope | `curl -s http://localhost/api/health` | `{"result":{"status":"ok"},"errors":[]}` |
| 3 | Laravel's own probe | `curl -s -o /dev/null -w '%{http_code}\n' http://localhost/up` | `200` |
| 4 | The queue worker is up | `docker compose ps queue` | State `running` (it runs `php artisan queue:work --tries=3 --timeout=120`) |
| 5 | Every container is up | `docker compose ps` | `app`, `queue`, `nginx`, `node`, `db`, `redis` all running; `db` healthy |
| 6 | Migrations applied | `make artisan CMD="migrate:status"` | Every migration `Ran` |
| 7 | Seeds applied | `docker compose exec -T db sh -c 'MYSQL_PWD=secret mysql -umarketing_ai marketing_ai -e "select name from roles"'` | `admin` and `user` |
| 8 | The suite passes | `make test` | All green |
| 9 | Vite is serving | `curl -s -o /dev/null -w '%{http_code}\n' http://localhost/@vite/client` | `200` — nginx proxies the dev server; `5173` is not published |

`GET /api/health` is deliberately unversioned — an infrastructure liveness probe
must not move when the API version does. Every other endpoint is under
`/api/v1`.

Optional: `docker compose --profile observability up -d` adds Loki, Promtail and
Grafana at <http://localhost:3000> (admin/admin).

---

## 6. First run, as a user

### Sign in

Open <http://localhost> and sign in with Google. There is **no email and
password** — the system has no passwords at all, by design: no reset flow, no
credential stuffing, less to get wrong. The first login creates the account, the
`account` record everything else hangs off, and assigns the role.

### Onboarding — four steps, all skippable

You land in a wizard rather than the dashboard. Each step has the same shape: a
visual guide showing exactly where to get the credential, a field or a connect
button, and a **live validation** that makes a real call to the provider and
tells you immediately whether it worked and, if not, the likely cause.

| # | Step | What you provide | How it is validated |
|---|---|---|---|
| 1 | **LLM key** | An Anthropic, OpenAI or Gemini key (§4.4) | A minimal, near-free call to the provider |
| 2 | **Apify key** | The personal API token (§4.3) | `GET /v2/users/me` |
| 3 | **Meta** | A **connect button**, not a key — the OAuth flow (§4.2) | `GET /me` plus a listing of your ad accounts |
| 4 | **Google** | A **connect button** — Drive with `drive.file`; YouTube optional in the same flow (§4.1) | The token exchange itself |

Rules that apply to all four:

- **Every step is skippable.** "Configure later" is always available.
- **Skipping locks features, it does not break anything.** On the dashboard, any
  feature whose credential is missing appears locked with a CTA, and that CTA
  reopens *exactly that step* of the wizard. No Apify key means competitor
  analysis is locked; no LLM key means everything intelligent is locked; no Meta
  connection means campaigns are locked; no Google connection means the asset
  library is locked.
- **The wizard is resumable.** Each step is persisted as completed, skipped or
  pending. Abandon it halfway and you come back where you left off.
- A configuration checklist stays visible showing what is still outstanding.
- The order is not arbitrary: LLM first because it is the simplest and unlocks
  the most, Apify second, then the two OAuth flows.
- TikTok, YouTube-as-a-channel and any future network are **never** part of
  onboarding. They live in **Settings → Integrations** as optional connectors.

### Then the brand profile, then the first strategy

Finishing (or skipping) the wizard lands you on **brand profile** creation — who
the brand is, its voice, its audience — which is the context every AI feature
reads before it generates anything.

From there you create your **first strategy**: the objective, the period and the
budget. A strategy is the root of the hierarchy — experiments hang off it, and
campaigns, content and reporting hang off those. Nothing in the four modules
(Competence Analysis, Content Planner, Campaign Manager, Reporting & Learning)
can run before one exists.

One invariant worth knowing on day one: **the assistant never executes a
mutation.** Anything that would change money or state — creating a campaign,
changing a budget, pausing an ad — comes back as a **proposal** you accept or
reject. There is no path from the AI to a live change without a human clicking
approve.

---

## 7. Troubleshooting

### Google returns `invalid_grant` after about a week

```json
{ "error": "invalid_grant", "error_description": "Token has been expired or revoked." }
```

**Cause.** The consent screen is still in **Testing**. Google issues refresh
tokens that expire in 7 days for external-type projects in Testing whenever the
scope set goes beyond name/email/profile — and `drive.file` does. It works, then
stops, a week later, for every user at once.

**Fix.** Google Cloud Console → **Google Auth Platform** → **Audience** →
**Publish app**, and confirm Publishing status reads **In production**. Then have
each affected user reconnect Google, which issues a fresh, non-expiring refresh
token.

`invalid_grant` is terminal — the app never retries it. It marks the connection
disconnected and asks for re-consent. Other documented causes, if you are already
in Production: the user revoked access; the token went unused for six months; the
account exceeded 100 live refresh tokens for this client id (the oldest is
silently invalidated); a Workspace admin restricted the service
(`admin_policy_enforced`).

### Meta returns a permissions error

**Cause.** In Development mode, Meta grants permissions **only to users holding a
role on the app**. If the person connecting is not an administrator, developer or
tester, the requested scopes are simply not granted, and the failure surfaces as
a permissions error rather than as "you have no role".

**Fix.** developers.facebook.com → your app → **App Roles → Roles** → add them as
administrator, developer or tester. They accept the invitation, then retry the
connection. App Review is *not* the answer here — it is only required to serve
people who will never hold a role.

**If the connection succeeds but the ad account list comes back empty**, the
permission was granted but the user has no access to any ad account. Check in
Business Manager that their user has access to the ad account you intend to use.

### A Gemini key is rejected with HTTP 400 instead of 401

**Cause.** Gemini does not follow the usual convention. An invalid key returns
**400 Bad Request** with `error.details[].reason == "API_KEY_INVALID"`, not 401.
Code that decides "is this key valid" from the status code alone will misread it.
The app inspects the reason, not the status.

**If instead you see `401 ACCESS_TOKEN_TYPE_UNSUPPORTED`:** your Google account
issued a key in the newer **`AQ.`** format, which the
`generativelanguage.googleapis.com` REST endpoint reportedly does not yet accept.
The app's format check deliberately allows both `AIza` and `AQ.` prefixes — a
strict `^AIza` check would lock out accounts that can only generate the new
format — so this failure comes from the provider, not from validation. Generate
an `AIza` key in AI Studio. *(Both the `AQ.` format and this rejection behaviour
are unverified — sourced from the Google AI Developers Forum, June–July 2026, not
from official documentation.)*

### `redirect_uri_mismatch`, or the OAuth callback 404s

**Cause.** The redirect URI must be **byte-identical** between the authorisation
request, the code exchange, and the value registered in the provider's console.
`http` vs `https`, `localhost` vs `127.0.0.1`, a different port, a trailing slash
— any of them fails.

Compounding it: the integrations OAuth flow builds its callback from the **named
route**, and `route()` builds URLs from **`APP_URL`**. So if you started the stack
on a non-default port (`HTTP_PORT=9000 make up`) without updating `APP_URL`, the
app will send Google a URI you never registered.

**Fix.**

1. Set `APP_URL` in `src/.env` to exactly the origin you browse.
2. Register **all** the Google callbacks, not just sign-in (§4.1 step 9).
3. Register the Meta callback (§4.2 step 5).
4. `make artisan CMD="config:clear"` and retry.

### `No application encryption key has been specified`

**Cause.** `APP_KEY` is empty. `make up` only generates it when the line does not
already read `APP_KEY=base64:`, so a hand-edited `src/.env` that kept `APP_KEY=`
will start without one.

**Fix.** `make artisan CMD="key:generate"`.

**Do not regenerate `APP_KEY` on an environment that already has data.** It is
what encrypts every stored user credential. Changing it makes every saved Apify
key, LLM key and OAuth token undecryptable, and every user has to reconnect
everything.

### `SQLSTATE[HY000] [1045] Access denied for user`

**Cause.** The database credentials drifted between the two sides — MySQL was
*created* with `MYSQL_*` values and Laravel is *connecting* with `DB_*` values,
and they no longer match.

Two ways this happens: you changed `DB_PASSWORD` in `src/.env` but not the
compose-level `MYSQL_PASSWORD` (or vice versa), or you changed `MYSQL_PASSWORD`
on a stack whose data volume **already exists** — the MySQL entrypoint only
applies those variables when it initialises an empty volume, so the container
happily starts with the *old* password.

**Fix.** Make `DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` in `src/.env` equal
`MYSQL_DATABASE`/`MYSQL_USER`/`MYSQL_PASSWORD`. If the volume already exists and
you truly want new credentials, either change the password inside MySQL, or
destroy the volume and start over — **which deletes all local data**:

```bash
docker compose down -v && make up && make artisan CMD="db:seed"
```

### Other things

| Symptom | Cause | Fix |
|---|---|---|
| A port is already allocated | Something else holds 80/3307/6380 | `HTTP_PORT=9000 make up` — and update `APP_URL` and the registered redirect URIs to match |
| Config changes have no effect | A cached config | `make artisan CMD="config:clear"` |
| Jobs never run | The `queue` container is down | `docker compose ps queue`, then `docker compose up -d queue` and check `make logs` |
| `make test` fails on a missing database | The testing schema is absent on an old volume | `make test` recreates it; if it persists, re-run `make up` |
| Files owned by root in `src/` | The image was built with the wrong UID/GID | `docker compose build --no-cache` — the Makefile exports your real UID/GID |

---

## 8. What is deliberately not here

**Production deployment.** Server provisioning, TLS, the `.env.docker` file on the
server, GitHub Actions secrets, `scripts/deploy.sh`, rollback and backups all live
in [`docs/deployment.md`](./docs/deployment.md). This document stops at a working
local environment.

Related reading:

- [`README.md`](./README.md) — the ten make targets and the repository layout.
- [`CLAUDE.md`](./CLAUDE.md) — the spec-driven workflow.
- [`.ai/architecture.md`](./.ai/architecture.md) — layering, the JSON envelope,
  how per-account credentials are resolved at runtime.
- [`spec/2026-08-23-initial-app-development/core.md`](./spec/2026-08-23-initial-app-development/core.md)
  — the product design this setup serves.
- `spec/2026-08-23-initial-app-development/research/` — the provider research every
  console path and quoted rule above comes from, each with its source links and
  its own UNVERIFIED section.
