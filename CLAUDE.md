# Everything Immersive (EI)

An event discovery and community curation platform for immersive experiences. Built with Laravel 12 + Vue 3 SPA, deployed via GitHub Actions.

## Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+), MySQL, Eloquent ORM
- **Frontend**: Vue 3 (Composition API, `<script setup>`), Tailwind CSS 3.4, Vite 5
- **Search**: Elasticsearch via Laravel Scout + Elastic Scout Driver Plus
- **Auth**: Laravel Sanctum (SPA), Socialite (Google, GitHub, Apple), passwordless email codes
- **Storage**: DigitalOcean Spaces (S3-compatible) for images, local filesystem
- **Sessions**: Redis. **Cache**: Redis. **Queue**: database. (Confirmed live via `php artisan about`; the legacy `QUEUE_DRIVER=redis` env key is ignored by Laravel 12, which reads `QUEUE_CONNECTION` → database.) ⚠️ Changing `SESSION_DRIVER` flips the session store and logs everyone out on the next `config:cache`. Testing uses array cache/session + sync queue.
- **Testing**: Pest PHP 3 with Laravel plugin
- **Code Style**: Laravel Pint (PSR-12), EditorConfig (4-space indent, LF line endings)

## Project Structure

```
app/
├── Actions/           # Business logic (Curated/, Search/, Admin/)
├── Console/Commands/  # Artisan commands (publish-embargoed, check-closing)
├── Http/
│   ├── Controllers/   # Organized by domain (Admin/, Api/, Auth/, Creation/, Curated/, Search/, User/)
│   ├── Middleware/     # BlockEditDuringMaintenance, AdminMiddleware, ModeratorMiddleware
│   └── Requests/      # Form validation (StoreEventRequest, StoreCommunityRequest, etc.)
├── Mail/              # Mailables (LoginCode, Comments, CuratorInvitation, etc.)
├── Models/            # Eloquent models organized by domain
├── Policies/          # Authorization (Event, Community, Post, Organizer, User, Conversation)
├── Rules/             # Custom validation (UniqueSlugRule)
├── Scopes/            # Global query scopes (Published, Rank, Admin, Date, CreatedAt)
├── Services/          # ImageHandler, NameChangeRequestService, EventScraperService
└── Traits/            # Favoritable
routes/
├── web.php            # Main web routes
├── api.php            # API endpoints with throttle groups
├── auth.php           # Authentication routes
└── curated.php        # Community/post/shelf/card routes
resources/
├── js/
│   ├── app.js                # Vue app entry point, async component registration
│   ├── Stores/               # Custom reactive stores (SearchStore, MapStore)
│   ├── composables/          # dateUtils.js, useSecureUrl.js
│   ├── GlobalComponents/     # Shared components (dropdown, pagination, TipTap, etc.)
│   └── PageComponents/       # Page-level components by domain (Nav/, Search/, Admin/, etc.)
├── css/               # Tailwind app.css + datepicker styles
└── views/             # Blade templates (master layout, pages, partials)
```

## Core Domain

### Key Entities
- **Event**: Core listing with status lifecycle (draft → in-review → published/embargoed), show types (specific dates, ongoing, always available, limited), location (in-person/remote), genres, advisories
- **Organizer**: Event-hosting groups with team members via `organizer_user` pivot (with roles)
- **Community**: Curated content groups with curator invitations, containing Shelves → Posts → Cards
- **Dock**: Homepage content sections linking to posts/shelves/communities/cards via polymorphic `association` pivot
- **User**: Roles via `type` char — `g`=guest, `u`=user, `c`=curator, `m`=moderator, `a`=admin

### Status Codes
- **Event status**: `d`=draft, `0`=new, `r`=**under review** (awaiting moderation — this is the approval queue, see `AdminEventController::getPending()`), `p`=published, `e`=embargoed, `n`=**rejected** (set by `reject()` via `App\Actions\Admin\ModerateSubmission`; the reason is sent to the owner as a message, there is no `rejection_reason` column). ⚠️ `r` is NOT "rejected" and `n` is NOT "other" — this file said so until 2026-08-26 and it's an easy way to read the approval queue backwards.
- **Event showtype**: `s`=specific dates, `o`=ongoing, `a`=always, `l`=limited
- **Content status** (organizer/community/post): `p`=published, `d`=draft, `r`=under review, `n`=rejected — same convention as events above (`AdminOrganizerController`/`AdminCommunityController` both set `n` on reject).

### Key Patterns
- **Action classes** for business logic instead of fat controllers
- **Global scopes** on models (LatestPublishedFirstScope, RankScope, DateScope, AdminScope). ⚠️ `LatestPublishedFirstScope` only sorts (`orderBy('published_at','desc')`) — it filters nothing. Anything that must show published events only needs its own `where('status','p')`. It was called `PublishedScope` until 2026-08-26, which repeatedly got mistaken for a visibility filter.
- **Polymorphic relationships** for Images, Videos, Favorites, NameChangeRequests
- **ImageHandler service** saves WebP + JPEG with thumbnails to DigitalOcean Spaces
- **Slug-based routing** (`getRouteKeyName() = 'slug'`) on Event, Organizer, Community, Category
- Vue components registered globally as `vue-{component-name}` with async loading
- Custom reactive stores (no Vuex/Pinia) using Vue `reactive()`
- Server passes data to frontend via `window.Laravel` object in Blade

## Development

```bash
# Dev server (Vite HMR on ei.test:5173)
npm run dev

# Run tests
php artisan test
# or: ./vendor/bin/pest

# Code formatting
./vendor/bin/pint

# Build (with environment confirmation prompt)
npm run build        # staging
npm run production   # production
```

### Environment
- Local dev domain: `ei.test`
- DB: MySQL (`ei` database) or SQLite for testing
- **Config source of truth = the live server `.env`** (excluded from the deploy rsync). `.env.example` (the only git-tracked env file) is the clean canonical template. The local `.env.prod`/`.env.stage`/`.env.local`/`.env.old` are **unused reference copies** — NOT consumed by the deploy, and internally stale (mixed Laravel-8 keys like `CACHE_DRIVER`/`QUEUE_DRIVER`, duplicate `SESSION_DRIVER`/`QUEUE_CONNECTION`). Don't trust them over the live server.
- Testing uses array drivers for cache/session/mail and sync queue

### Scheduled Commands
- `ei:publish-embargoed` — Every 2 hours, publishes events past embargo date (timezone-aware)
- `ei:check-closing-events` — Daily, notifies creators of events closing soon (currently disabled)

## Deployment

GitHub Actions (`.github/workflows/deploy.yml`). ⚠️ **Trigger → target:** a `push` to `main` deploys to **DEV** (`/var/www/secret`); **production** (`/var/www/ei`) is a *separate manual* trigger — `gh workflow run deploy.yml --ref main -f environment=production`. Steps:
1. Build frontend assets with environment-specific Vite vars
2. rsync to server (excludes `.git`, `node_modules`, `vendor`, `storage`, and every `.env*` except `.env.example`)
3. `composer install --no-dev`, `php artisan migrate --force`
4. Cache config/routes/views (`config:cache` activates the server `.env` — changing `SESSION_DRIVER` there logs everyone out), restart queues
5. Post-deploy smoke test curls the live URL (catches deploys that "succeed" but break the site)

⚠️ **No test step anywhere in this pipeline** — neither `./vendor/bin/pest` nor `npm run test:js` ever runs in CI. The ~1900-test suite only protects you if someone runs it locally before pushing; a regression that slips past that goes straight to DEV (and to prod on the manual trigger) with only the smoke test's HTTP-status check to catch it. Known, deliberately not auto-added (2026-08-24) — wiring it in is a real design decision (block the deploy on failure, or just warn? how much does it add to deploy time?), not something to bolt on without discussing first.

### Server access
- Host, SSH command, bucket names and anything else infrastructure-specific live in the git-ignored `CLAUDE.local.md` (this repo is public). Never add server addresses, hostnames, credentials, key ids or personal email addresses to any tracked file. Single DigitalOcean droplet hosts both targets: **prod** at `/var/www/ei`, **dev/staging** at `/var/www/secret`.
- The server `.env` is the source of truth for runtime config and is **excluded from the deploy rsync** — editing it then deploying (which runs `config:cache`) is what activates `.env` changes (and can flip the session store / log everyone out; see Sessions note above).
- **Queue workers**: `ei-queue.service` (prod) / `ei-queue-dev.service` (dev) — systemd units running `php artisan queue:work database --sleep=3 --tries=3 --timeout=60 --max-time=3600 --memory=192` as `www-data`, `Restart=always`, `StartLimitIntervalSec=60`/`StartLimitBurst=5` (stops restart-looping forever on a persistent config error), distinct `SyslogIdentifier` per environment, enabled on boot. Added 2026-08-24 (hardened same day after a Codex infra review); before that no worker existed anywhere despite `QUEUE_CONNECTION=database` and `ShouldQueue` notifications — jobs just silently piled up in the `jobs` table forever. `--timeout=60` is deliberately kept below `DB_QUEUE_RETRY_AFTER` (unset, defaults to 90 in `config/queue.php`) — raising one without the other risks a job running twice (duplicate email). Deploy's `php artisan queue:restart` step (signals a graceful restart so new code takes effect without dropping in-flight jobs) no longer swallows its own failure — as of 2026-08-24 it waits 8s then hard-fails the deploy (`exit 1`) if `systemctl is-active` doesn't confirm the worker came back. Known remaining gap: that check can't distinguish "worker actually crashed on the new code" from "worker's still finishing a job that started before the restart signal" (systemd reports the pre-restart process as active either way) — closing that needs comparing the worker's PID before/after rather than just an active/inactive check, not done yet. Both workers run as `www-data` on the same droplet as prod/dev PHP-FPM (no stronger isolation) — known, not yet addressed. Check with `systemctl status ei-queue.service` (prod) / `systemctl status ei-queue-dev.service` (dev), same pattern for `journalctl -u`.

## MCP server (AI assistants)

- **Endpoint** `/mcp` (`routes/ai.php`, server `app/Mcp/Servers/EiServer.php`, 20 tools under `app/Mcp/Tools/`). Auth is **OAuth 2.1 via Laravel Passport** (`api` guard): a client discovers the flow from the 401's `WWW-Authenticate`, registers itself (`POST /oauth/register`, redirect domains allowlisted in `config/mcp.php`), sends the user to `/oauth/authorize` (`routes/oauth.php`, our consent view `resources/views/oauth/authorize.blade.php`), exchanges the code with PKCE at `/oauth/token`. Personal access tokens from the API keys page work the same way for scripts. Sanctum is SPA-cookie-only now; bearer tokens never authenticate `auth:sanctum` routes.
- **Scopes are the privilege boundary.** `mcp:use` = the token owner's own organizers. `mcp:moderate` = moderator/admin cross-tenant powers; a client can never request it (the consent middleware refuses the scope), and it reaches a token only when a moderator ticks "Include moderator powers" on the consent screen for that connection (`Oauth\ApproveAuthorizationController`) or mints a key with moderator powers. `User::isModerator()`/`isAdmin()` are credential-aware (`credentialAllowsModeration()`): a web session behaves as always, an API token needs the scope. ⚠️ Any new moderator-only branch must key on those two methods, never on `type` directly.
- **Launch switch** `MCP_PUBLIC` (`services.mcp.public`, default false): while off, only moderators can connect an assistant or mint a key.
- **Keys** live in `storage/oauth-*.key` (rsync-excluded, git-ignored), created by `php artisan mcp:oauth-setup` in the deploy; never regenerate them on a live environment. Tests use a throwaway pair under `storage/framework/testing/oauth` (`tests/Pest.php`).
- **Audit trail** `mcp_tool_calls` (one row per request: user, token, app, tool, status, duration). Nightly: `passport:purge`, `mcp:prune-oauth-clients`.
- **Tests**: `tests/Feature/Mcp/` — `McpAuthTest` (HTTP auth), `OauthFlowTest` (the whole flow), `McpCrossTenantTest` (every tool from the wrong side of the boundary). Real-client check: `claude mcp add --transport http ei http://ei.test/mcp` and approve in the browser.

## First-party analytics

- **What**: cookieless counts of searches (place, filters, result count), event page views (with where the visitor came from), ticket clicks and search-result clicks, in `analytics_events`. Read them in Admin > Analytics or the `get-site-analytics` MCP tool (needs `mcp:moderate`); both use `App\Actions\Analytics\SiteAnalyticsReport`, people only (`bot = 0`), typed searches only (`source = list`; map pans are `map`). ⚠️ The report is capped at 90 days: prod writes ~15k rows a day, so a longer range needs a daily rollup table first. "Visitors" are visitor-days (the salt changes daily).
- **Write path**: `App\Support\Analytics\Analytics::record()` pushes one JSON note to a Redis list (RPUSH+LTRIM+EXPIRE: capped 50k, gone a day after the last push) and never throws. `ei:analytics-flush` (every minute) pops atomically (LPOP count, put back if the insert fails; with analytics off it deletes the buffer), hashes IP+UA with a per-day salt, flags bots and inserts; raw IP/UA never reach MySQL. `bot` is a bitmask (crawler, no UA, over 300/day per visitor or 1000/day per IP, hosting network): rows are flagged, never dropped. Result clicks arrive via `navigator.sendBeacon` at the stateless `POST /api/analytics/search-click`.
- **Geo**: `ei:analytics-geo-update` (daily, downloads monthly) keeps the free DB-IP Lite country/ASN databases in `storage/app/geo`; hosting ASNs are listed in `config/analytics.php`. Missing files just mean no country and no datacenter flag.
- **Phase 2 captures** (each off until switched on: `ANALYTICS_PAGE_VIEWS` etc. in config `analytics.capture`, or `php artisan ei:analytics-capture <name> on|off|default`, an override kept in `storage/app/analytics-capture.json` (not the cache: Redis evicts) needing no deploy): `page_views` (RecordPageView middleware, allowlisted public routes, pushed in terminate() after the response), `utm`, `device` (at flush), `city` (DB-IP City Lite at flush, downloaded only while on), `duration` (each hide sends the running visible total to `POST /api/analytics/page-leave`, after 5 visible s, at most 10 per view, within 23 h; the rollup keeps the largest), `live` (Redis sorted set kept by the flusher), `nav_search` (public nav only, `nav=1`), `js_ping` (needs `page_views`: as soon as the script runs and the page is visible (not prerendering; retried on pagehide), the browser sends `POST /api/analytics/page-ping` with its view id and `navigator.webdriver`; the flusher never stores it as a row but sets `analytics_events.js` = 1 on the page view (0 = asked, waiting; NULL = not asked), ORs `BOT_AUTOMATION` (32) onto every row of that visitor's UTC day so far when webdriver (and, through cache key `analytics:automated:{day}:{visitor}`, onto the rest of that day's rows as they are flushed), own limiter `analytics-ping` (240/min per IP), and retries a ping whose view is not written yet for 3 runs). ⚠️ Rollback: before deploying code older than the load ping, switch `js_ping` off and let one flush run drain the buffer: the old flusher would insert leftover `page_ping` notes as rows. Flusher, rollup and readers check `Analytics::hasConfirmationColumns()` (once per process) and leave `js`/`js_visitors`/`engaged_visitors` alone until the migration has run. The privacy page names each one while it is on, and after it goes off for as long as the daily totals hold its data from the last 13 months (`Analytics::recentlyCaptured`, however it was switched off). Turn them on one at a time and watch FPM, Redis memory and rows/day.
- **Real visitors, side by side** (compare for a few weeks before replacing anything): `Analytics::visitorFlagsSql()` defines per visitor-day *browser confirmed* (only a people page_view with `js = 1`: every other beacon is a POST any script can send) and *engaged* (confirmed AND GA4 style activity: a leave of 10+ s, a ticket/result click, nav typing or a typed search, or 2+ page views); a visitor-day with any `BOT_AUTOMATION` row is neither. Beacons (page_leave, search_click) count for their view's or search's visitor (`Analytics::visitorFlagsQuery`), and the report's people line counts only `Analytics::SERVER_TYPES`, so a leave just after midnight UTC is no phantom visitor-day. The daily totals carry them as `js_visitors`/`engaged_visitors` (NULL on days the ping was off or switched on or off part way, and for bots: a day is measured only when it has people page views and none has `js` NULL); the report (VERSION 12) as `visitors_on_measured_days`/`confirmed_visitors`/`engaged_visitors` per kind and in `totals.people` (+ `measured_since`; measured days come from the daily totals, so today counts after its hourly rollup; flags built once per range into a temporary table), countries as `{visitors, visitors_on_measured_days, confirmed}`; MCP metrics `confirmed_visits`/`engaged_visits` with `measured_since` and `visits_on_measured_days`. Only ever compare them with visits of measured days.
- **Daily totals** `analytics_daily` (day, type, dim, key, bot → hits, visitor-days, time on page; every page view or event view is type `view`, map pans `map_search`), built by `ei:analytics-rollup` (idempotent per day; nightly for the last 3 days, hourly for today; each run also fills any day with raw rows but no totals (the days before deploy, or ones the scheduler missed)); paths kept only as edges >= 5 visitors, two page views at most 30 minutes apart; prune and rollup share one lock. Bot raw rows are pruned after 30 days, people's after 13 months; daily totals stay, except typed/sent text and page addresses (query, ref, city, utm, path) shared by fewer than 3 people, which goes at 13 months too. MCP tools `analytics-trend`, `analytics-top`, `analytics-paths`, `analytics-for`, `analytics-live` read them (moderators, bounded args, 5 s query cap, 10-min cache, visitor text wrapped as `{"visitor_text": ...}`).
- **Switches**: `ANALYTICS_ENABLED` (default on for production/local/testing, off on staging, which runs no scheduler). Rows are pruned after 13 months (`ei:analytics-prune`). Google Analytics loads only while `ANALYTICS_ID` is set, Umami only while `UMAMI_WEBSITE_ID` is set; the privacy page names whichever is on.

## Google Search Console

- **What**: Google's own numbers for the site (searches that led here, the pages Google sends people to, impressions, clicks, CTR, average position) in `search_console_daily` (day, dim `all`/`query`/`page`/`country`/`device`/`query_page`, key → clicks, impressions, `position_sum` = position x impressions so averages over days stay right). Pages are stored as paths for our own host, with no query string, fragment or trailing slash, so variants add up (full address for any other host), countries as alpha-2, `query_page` keys as `"query > page"` (94 chars a side). Shown as the "From Google" block on Insights (`AnalyticsGoogle.vue`, sections `google_queries`/`google_pages`, `GET /api/admin/analytics/google`) and the moderator-only MCP tool `search-console` (searches wrapped as `{"visitor_text": ...}`); both read `App\Actions\Analytics\SearchConsoleReport` (period = N days ending on the newest imported day, since Google lags 2 to 3 days, never starting before the first imported day (`data_since`); past 90 days the series is weekly, rows `{week, days, ...}` dated by their Monday, the first and last possibly partial; `previous` is null when the days before would start earlier; primary key (dim, day, key) serves every read, (day) the importer's delete; 10-min cache keyed by the period itself so rows and their period label always come from one read (`lastPeriod()`), 5 s query cap, a slow read answers 503). Pages under `/events/` or `/organizers/` that match no current one (a deleted event's slug is released, a renamed one's address changes, and a page whose event or organizer is not published (status other than `p`) does not open) are kind `gone` ("Not a live event or organizer page"), not passed off as other pages. Pages can add up to MORE than the site totals (Google counts each page shown in a result list; totals count the site once per search), searches to less (rare ones hidden). In MCP answers a page is raw only when it is a current event or organizer path; any other address is visitor text too; `order=impressions` lists the most shown instead of the most clicked. Google's days are Pacific time.
- **Import**: `ei:search-console-import` (nightly 04:10 UTC, last 5 days ending 2 days ago; `--from=Y-m-d` backfills up to 16 months, `--to`, `--days`). Each day is deleted and re-inserted in one transaction; a failed day is reported and skipped (its old rows stay); a 401/403/404, a refused key, or Google still unavailable after 5 tries (429/5xx/dropped connection, on the API or the token endpoint, with backoff) is reported once and stops the run. 250 ms between calls. Auth is `App\Support\Google\ServiceAccountToken` (an RS256 JWT signed with openssl, no Google SDK; access token cached 50 min). Web requests never call Google.
- **Monthly totals** `search_console_monthly` (month = first day, dim, key; PK (dim, month, key)): rebuilt by the importer for every month it stored a day in (also when a run stops early), or all at once with `ei:search-console-import --rebuild-months` (no Google call; run it once after the first deploy of this table). Readers take whole calendar months inside a period from it and only the partial edge days from the daily table (UNION ALL, then GROUP BY key); if any of those months is missing there they read days only, with the same answer. Lines that are one key in the case/accent-insensitive column are shown with their first spelling in byte order (`SearchConsoleReport::SPELLING`), so both ways agree. On the real 11 months (2M daily rows) a whole-range `queries` read takes ~0.4 s and `query_pages` ~1.3 s (days only: 0.9 s / 2.3 s).
- **Switch**: the importer runs (and is scheduled) only when both `SEARCH_CONSOLE_CREDENTIALS` (path to the service account JSON key, e.g. `storage/app/google/search-console.json`: storage is git-ignored and never rsynced, so the key stays on the server) and `SEARCH_CONSOLE_SITE_URL` (e.g. `sc-domain:everythingimmersive.com`) are set. The readers (Insights block, sections, MCP tool) never need the key: they show while `SEARCH_CONSOLE_SITE_URL` is set or any rows were imported, and are hidden / say not configured otherwise.
- **Setup**: enable "Google Search Console API" in the GCP project and add the service account as a restricted user of the Search Console property (project id and account email in `CLAUDE.local.md`; both done 2026-10-03); put its key at the credentials path on the server, readable by the user that runs `php artisan schedule:run` (root's cron on this server, so root-owned, mode 600; PHP-FPM never reads it); set the two env vars and deploy (`config:cache`); run `php artisan ei:search-console-import --from=<16 months ago>` once.

## Important Notes

- **NEVER `git push` or trigger remote deploys without explicit user permission.** Local commits are fine. `git push`, `gh workflow run`, and anything that hits CI/CD or prod requires an explicit "push" / "ship" / "deploy" from the user each time. This applies even if the previous push went green and the next change looks small. Don't auto-push between iterations during a review cycle.
- Events use `SoftDeletes` — always check for soft-deleted records
- **90-day edit lock**: a published/embargoed event whose `closingDate` is more than `Event::EDIT_WINDOW_DAYS` (90) in the past is read-only to organizers on every write path (hosting controller + MCP tools) via `Event::isEditLockedFor()`; moderators/admins exempt, drafts never locked, `duplicate` stays open. Appended to event JSON as `isEditLocked`; the dashboard shows a modal offering Duplicate, and `/hosting/event/{slug}/edit` redirects there with `?locked=`.
- Event search index only includes published, non-trashed events (`shouldBeSearchable()`). Since 2026-09-13 every index write for an Event (Scout's observer or an explicit `Event::syncSearchIndex()`) becomes "reconcile event N": `SyncEventSearchIndex` re-reads the row when it *runs* and indexes or removes accordingly (tries 3, backoff 30s/120s), so a delayed retry can't resurrect a deleted event; with `SCOUT_QUEUE=true` on the servers that is a queued job for the `ei-queue*` workers, with it off (local, tests) it runs inline. `UpdateEventAction` batches with `Event::deferringSearchSync()` (one job per save, after commit; enter it at transaction level 0, see its docblock). If Elasticsearch is down for longer than the backoff, jobs land in `failed_jobs`: `php artisan queue:retry all` once it is back (safe: they reconcile from current state). Reads go through `App\Support\Search\SearchGuard`: the search page returns empty results (+ `search_unavailable: true`, also set when only the price or pins read failed) and the nav autocomplete returns `[]` instead of 500 while the cluster is unreachable.
- **Show history** (since 2026-09-28): a show day more than a year old (`Show::HISTORY_AFTER_YEARS`) is not a `shows` row but part of `events.show_history`, exact weekly runs packed by `App\Support\ShowHistory`. Saves put old days there (`Show::saveShows`), `ei:fold-show-history` (weekly, running events only; `--all` for finished runs, dry run without `--apply`) moves rows as they age, and the newest show day always stays a row. Undo: `ei:unfold-show-history --apply` turns history back into rows (the migration's `down()` refuses while any history exists, since folded rows are deleted). MCP `update-event` keeps older days implicitly (`remove_older_show_days` / `replace_older_show_days`), and an `ongoing_config` recipe never adds days older than a year that are not already history (closures). So a schedule = rows + history: anything that counts, lists or dates a run's shows must read both (see the readers in `EventController::loadShowsForPage`, `GetEvent::historySummary`, `showHistoryDays()` in `useShowDates.js`). Rows are capped at `Show::MAX_ROWS` (9,500, the search index limit); a whole schedule at `RecurringDates::MAX_OCCURRENCES` (40,000); staff may reach `Show::STAFF_LOOKBACK_YEARS` (100) back.
- Image paths stored on models (`largeImagePath`, `thumbImagePath`) AND in polymorphic `images` table
- The `closingDate` on events determines visibility; shows have individual dates
- API rate limits vary by endpoint group (30-600 requests/min)
- Admin/moderator routes require `auth:sanctum` + `moderator` middleware
- Timezone handling is critical — events store their own timezone, embargo publishing respects it
