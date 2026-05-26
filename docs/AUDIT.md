# EI Codebase Audit

**Last fresh audit:** 2026-05-24
**Method:** four specialist sub-agents (security, performance/DB, frontend, backend code-quality + test gaps) ran in parallel against the post-fix codebase. Critical findings were verified against the actual files before being recorded here. The previous audit's "already fixed" content has been pruned — see git history (`git log --oneline -30`) for the resolved items.

This document is the **open work list**. Severity ranking is impact × likelihood × ease of exploitation, not just blast radius. Each finding cites file:line so you can jump straight in.

---

## Recently fixed (one-line summary, see git log for details)

- **Critical:** XSS in blurbs (C1) and messages (C2); foundational test suite (C3).
- **High:** all 13 — switchTeam intent (H1), pagination races (H2), event-create double-submit (H3), conversation-create race + unique index (H4), null guard in `canAppendMessage` (H5), conversations user index (H6 — FK still deferred), `withCount` for click stats *in admin index only* (H7 — see new finding H-Q4 below for the bit missed in `show()`), `track_clicks` indexes + daily archival cron (H8), `belongsToOrganization` N+1 (H9), organizer event eager-load limit (H10), event-table indexes (H11), global Vue error handler routing to Sentry (H12), test-coverage foundation (H13 — 146 tests passing).
- **High (fresh audit, batch 1):** H-Q1 silent message-email bug; H-Q2 exception leak in 8 responses; H-Q3 dead `restore()` method deleted; H-Q4 `withCount` on `AdminEventController::show`; H-M1 misnamed migration renamed (with prod `migrations` table SQL fix queued); H-F1 `AbortController` timeout on geonames fetch.
- **High (fresh audit, batch 2 — security):** H-S1 post-update allow-list (no more cross-community reparent / self-publish); H-S2 card-update allow-list + scoped route binding (no more cross-post IDOR); H-S3 SafeUrlValidator blocks SSRF against private/loopback IPs in scraper; H-S4 verified-email gate on Google/Apple/GitHub OAuth callbacks (GitHub adds `user:email` scope + `/user/emails` check); H-S5 stray `v-html` wrapped in `sanitizeBlurb`; H-S6 GeoNames moved to a server-side proxy route, username out of JS bundle.
- **Medium:** 9 — autoLogin email validation (M2), CSRF null-check (M3), organizer name lookup trim (M4), CORS allow-list (M5), axios timeout (M6), SearchStore Set (M7), six-month calc (M8), `isValidTimezone` wire-up (M14), name-change throttle (M15), and `showtype_config` persistence (M11) — left alone: M9 image dimensions, M12 attribute caching, M1 6-digit codes. Deferred: M10 (dates-wizard refactor needs splitting), M13 (cascade soft-delete needs child models to have SoftDeletes first).
- **Low:** all 5 — map fallback (L1), `crypto.randomUUID` (L2), 91 `console.log` purge (L3), date-picker `aria-*` (L4), dead password-auth controllers deleted (L5).
- **Plus:** TikTok URL widening, HostController null-organizer fix, Sentry SDKs wired, mews/purifier + DOMPurify wired.

---

## Post-deploy verification checklist

Run through this after each production deploy that includes any of the above fixes. Tick boxes as you go.

### Pre-deploy (run BEFORE the next prod deploy)

- [ ] **H-M1 migration tracker SQL** — On prod DB, run this *before* `php artisan migrate`:
  ```sql
  UPDATE migrations
  SET migration='2025_11_07_222950_add_start_date_to_events_table'
  WHERE migration='2025_11_07_222950_add_showtype_config_to_events_table';
  ```
  Without this step, the deploy's `migrate` will see the renamed file as new, try to re-add `start_date`, and fail with a duplicate-column error.

### Local dev (do now, one-time)

- [ ] `php artisan migrate` on local — adds `showtype_config` column to local `ei` DB (M11; tests already pass because `ei_testing` is migrated fresh, but local dev DB hasn't run it yet).

### Pending — to verify on next prod deploy

- [ ] **TikTok URL widening (`videos.url` → TEXT)** — Verify `mysql -uroot ei -e "SHOW COLUMNS FROM videos LIKE 'url'"` shows `text`. Live test: paste a long TikTok share link into an event's videos.
- [ ] **HostController null-organizer fix** — Visit `/hosting` while logged in; watch Sentry for 24h for any `Attempt to read property "status" on null` recurrence.
- [ ] **Sentry backend** — temporarily add `Route::get('/sentry-test', fn() => throw new \Exception('Sentry smoke'));`, deploy, hit it, delete the route, redeploy. Verify the issue appears in the Laravel project.
- [ ] **Sentry frontend** — in browser console, `setTimeout(() => { throw new Error('frontend sentry smoke'); }, 0);`. Verify in the Vue project.
- [ ] **C1 blurb sanitization** — view a curated post with bold/italic/link/heading — should still render; put `<img src=x onerror=alert(1)>` into a blurb — no alert.
- [ ] **C2 message sanitization** — multi-line messages keep line breaks; `<script>alert(1)</script>` renders as text.
- [ ] **H4 conversations unique index** — pre-flight aborts if dupes exist; otherwise `SHOW INDEX FROM conversations WHERE Key_name = 'conversations_participants_unique';` returns a row.
- [ ] **H7 admin events page perf** — `/admin/manage/events` loads noticeably faster.
- [ ] **H8 click archival cron** — after 24h, check `storage/logs/archive-clicks.log` for a "Deleted N rows" entry. Or run `php artisan ei:archive-clicks --days=999999` immediately to confirm wiring.
- [ ] **H12 Vue error handler** — `setTimeout(() => { throw new Error('vue smoke'); }, 0);` shows `[Vue] …` in console AND lands in Sentry.
- [ ] **H-Q1 message email** — send a message to a user whose `unread` is NULL (e.g., someone caught up on inbox) and confirm they receive the "new message about …" email. Send a *second* message before they read the first and confirm no second email fires.
- [ ] **H-M1 migration rename — RUN BEFORE DEPLOY.** On prod DB, run: `UPDATE migrations SET migration='2025_11_07_222950_add_start_date_to_events_table' WHERE migration='2025_11_07_222950_add_showtype_config_to_events_table';`. Without this, `php artisan migrate` during deploy will try to re-run the renamed file and fail because `start_date` already exists. (Local dev DB already updated.)
- [ ] **H-F1 location timezone timeout** — open the location wizard step, drop a pin, confirm timezone autofill works under normal conditions; with browser devtools' "Offline" mode on, confirm the UI doesn't hang past 8s.
- [ ] **H-Q2 error-leak cleanup** — trigger a 500 (e.g., temporarily make an organizer update fail via DB constraint) and verify the JSON response body has *no* `error` field, only `message`. Confirm the Sentry issue still gets the full trace.
- [ ] **H-Q4 admin event show perf** — load `/admin/manage/events/{slug}` and confirm `total_clicks` / `unique_visitors` still render on the page (proves the `loadCount` swap didn't break the shape the frontend reads).
- [ ] **H-S1/H-S2 curated allow-list** — as a curator, attempt to POST `community_id` to a post update / `post_id` to a card update. Confirm both fields are silently ignored (response 200 but fresh model still has the original parent). Also confirm `/communities/A/posts/{post}/cards/{card_from_another_post}` returns 404 (scopeBindings working).
- [ ] **H-S3 scraper SSRF guard** — `GET /api/scraper/test?url=http://169.254.169.254/` returns 422 with "URL is not allowed" message. A legitimate URL still works.
- [ ] **H-S4 OAuth verified-email gating** — happy path: log in with Google as usual (Gmail always verified → works). GitHub login still works *only if* primary email is verified. (Optional negative test: temporarily mark your GitHub primary email as unverified at github.com/settings/emails, try login — should get the "verify your primary email" error.)
- [ ] **H-S5 card-edit blurb sanitization** — open a card with HTML in its blurb, hit Edit Mode and back. Try saving `<img src=x onerror=alert(1)>` — no alert.
- [ ] **H-S6 geonames proxy** — set `GEONAMES_USERNAME=chgrim` (or whatever username after rotation) in server `.env`. `php artisan config:cache`. Drop a pin in the location wizard; verify the timezone autofills. View Network tab — request should go to `/api/geonames/timezone`, NOT `secure.geonames.org`. Then **rotate the username** (geonames.org allows free creation of new usernames; the old one is in the published JS bundles).

### Pending manual actions (CR3 — secret rotation)

`.env.local` was removed from git tracking in commit 70a502b, but **past commits still contain it**, so every credential below should be considered leaked since whenever it was first committed. Rotate in order, then update the server's `.env` directly (rsync excludes `.env` from deploys):

- [ ] **`OPENAI_API_KEY`** — direct billing exposure. platform.openai.com → API Keys → revoke + create new. *(Highest urgency.)*
- [ ] **`DO_ACCESS_KEY_ID` / `DO_SECRET_ACCESS_KEY`** — writes/deletes on `ei-test` Spaces bucket. cloud.digitalocean.com → API → Spaces Keys.
- [ ] **`AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY`** — verify what they do (SES? backups?). Rotate via IAM, or delete the keys if unused.
- [ ] **`GOOGLE_CLIENT_SECRET`** — console.cloud.google.com → APIs & Services → Credentials → reset secret.
- [ ] **`GITHUB_CLIENT_SECRET`** — github.com/settings/developers → your OAuth app → generate new.
- [ ] **`APPLE_CLIENT_SECRET`** — Apple developer console; regenerate the signed JWT secret.
- [ ] **`MIX_GOOGLE_LOC_KEY`** — Google Maps. Same key is also baked into the production frontend bundle as `VITE_GOOGLE_MAPS_KEY`, so domain-restrict in GCP rather than relying on rotation alone.
- [ ] **`APP_KEY`** — Laravel encryption key. Rotation invalidates every active session (everyone logged out on next request). Lower urgency; schedule for a low-traffic window. `php artisan key:generate --show` to generate without overwriting `.env`.
- [ ] **`MAIL_PASSWORD`** — Mailtrap sandbox, low risk (can't send real mail). Rotate when convenient.
- [ ] **`GEONAMES_USERNAME`** — *new* env var introduced by H-S6 fix. The current value (`chgrim`) was in published JS bundles for the lifetime of the old `location.vue`, so rotate to a fresh username on geonames.org and update prod `.env` (account creation is free).
- [ ] **(Optional)** Add a `gitleaks` GitHub Action or pre-commit hook so future secret commits get blocked.

### Recurring

- [ ] Weekly: open both Sentry projects' Issues tabs, triage anything new.
- [ ] After each deploy: `tail -100 /var/www/ei/storage/logs/laravel.log | grep -E "ERROR|CRITICAL"`.

### Rollback

The deploy workflow doesn't auto-rollback. To revert:
1. Find the last good commit on `main`.
2. `workflow_dispatch → production` against that commit, OR `git revert <bad> && git push`.
3. Migration problem: SSH in, `cd /var/www/ei && php artisan migrate:rollback --step=N` *before* re-deploying.

---

## Critical — fix this week

_All 3 fixed in code on 2026-05-24 (commit 70a502b). **CR3 still needs out-of-band secret rotation — see "Pending manual actions" checklist near the top of this doc.**_

### CR1. Mass-assignment lets event owners self-publish
- **Where:** `app/Http/Requests/StoreEventRequest.php:117` (rule is `'status' => 'sometimes|string'` — no `in:` constraint); exploited via `app/Http/Controllers/Creation/HostEventController.php:115` (`$event->update($validatedData)`). `Event::$fillable` (`app/Models/Event.php:37`) includes `status`.
- **What:** Any user who passes the `can:manage` policy can POST `status: "p"` to `/api/hosting/event/{event}` and immediately publish — bypassing the entire admin approval workflow.
- **Verified:** ✓ confirmed by direct file read.
- **Fix:** change the rule to `'sometimes|string|in:d,0,r'` (or drop `status` from the request entirely and let only `Show::updateEvent` and `AdminEventController` set it).
- **Effort:** 5 minutes + a regression test that POSTs `status: 'p'` as an organizer member and asserts 422 or that the value is silently dropped.

### CR2. Moderators can promote themselves (or anyone) to admin
- **Where:** `app/Http/Controllers/Admin/AdminUserController.php:50` validation accepts `type` in `['a', 'm', 'c', 'g']`. Route `routes/api.php:189` (`PATCH /api/admin/manage/users/{user}`) is gated by `moderator`, not `admin`.
- **What:** Any moderator can update any user (including themselves) to `type = 'a'`. Full privilege escalation from moderator → admin. The `destroy()` method at line 73 *does* guard admin deletion, but `update()` has no such guard.
- **Verified:** ✓ confirmed by direct file read.
- **Fix:** Either gate the route with the `admin` middleware (cleanest), or refuse `type` changes inside `update()` when `auth()->user()->type !== 'a'` (and refuse self-edits on `type` either way).
- **Effort:** 10 minutes + a test that a moderator gets 403 when posting `type: 'a'`.

### CR3. Secrets committed to repository
- **Where:** `.env.local` is tracked by git (`git ls-files .env.local` returns it — `.gitignore` lists it, but the file was committed before being ignored).
- **What:** Real-looking credentials in repo: `DO_ACCESS_KEY_ID=DO004YL84U4DBMWWWHKK`, `MIX_GOOGLE_LOC_KEY=AIzaSy…`, Mailtrap creds, `APP_KEY=base64:…`. The DO key has write/delete on `ei-test` Spaces. Anyone with the GitHub repo (collaborators, anyone the repo is ever made public to, anyone who clones a fork) has these.
- **Verified:** ✓ confirmed by `git ls-files .env*`.
- **Fix:**
  1. `git rm --cached .env.local` and commit.
  2. **Rotate** every secret in that file — DO key, Google Maps key (also strip the `MIX_` prefix since Laravel Mix is no longer used), Mailtrap, `APP_KEY` (note: rotating APP_KEY invalidates all existing signed routes, encrypted cookies, sessions — coordinate with users).
  3. Add a CI guard: `gitleaks` action on PRs, or pre-commit hook.
- **Effort:** 30-60 min including key rotation in each provider's console.

---

## High — fix this sprint

### Security

- ~~**H-S1. Curators can mass-assign post `status` and reparent posts cross-community.**~~ **FIXED** — `PostActions::update` now uses `$request->only(['name','blurb','shelf_id','order','type','event_id','image_type'])`. `community_id`, `status`, `user_id`, `slug` no longer reachable from the update payload.
- ~~**H-S2. Cards modifiable across post/community boundaries (IDOR + mass-assignment).**~~ **FIXED** — `routes/curated.php` card group now `->scopeBindings()`, so Laravel verifies `card.post_id = post.id` before route resolution. `CardActions::update` switched to `$request->only([...])` excluding `post_id`.
- ~~**H-S3. SSRF in `EventScraperController`.**~~ **FIXED** — new `app/Services/EventScraper/SafeUrlValidator` blocks non-http(s) schemes, literal localhost hostnames, and any host that resolves to a loopback / private / link-local / cloud-metadata IP. Wired into `extract()`, `test()`, and (defense-in-depth) `GenericAIScraper::fetchPage`. 13 unit tests cover the matrix.
- ~~**H-S4. OAuth callbacks auto-link by email — account takeover risk.**~~ **FIXED** — Google + Apple callbacks now reject if `email_verified` is missing/false; GitHub callback requests the `user:email` scope, calls `/user/emails`, and only proceeds if the account has a `primary && verified` row. The email used for user lookup/create on the GitHub path is the verified one, not whatever the default endpoint returned.
- ~~**H-S5. XSS — unsanitized `v-html` in `card-edit.vue:380`.**~~ **FIXED** — wrapped in `sanitizeBlurb(card.blurb)`. (Import was already present from the line-140 fix.)
- ~~**H-S6. Hardcoded GeoNames API username (`chgrim`) in client bundle.**~~ **FIXED** — new `GET /api/geonames/timezone` route proxies the call server-side using `services.geonames.username` (env-backed). Frontend updated; username no longer in the JS bundle. Existing key needs rotation since the bundle has been deployed (note added to CR3 rotation list).

### Performance

- **H-P1. `User` model auto-loads 3+ count queries on every serialize.** `app/Models/User.php:60-69` has `$appends = ['hasCreatedOrganizers','hasMessages','isCommunityMember', …]` + `$with = ['organizer']`. Every JSON response (every paginated list, every conversation thread) fires `teams()->count()`, conversation count, `communities()->exists()` per row. `AdminUserController::index` paginates 20 → 60+ extra queries. **Fix:** drop appends, introduce `UserResource` with explicit fields.
- **H-P2. `ListingsController` runs every Elasticsearch search twice.** `app/Http/Controllers/Search/ListingsController.php:295-309` and `380-394` run the full query once for `paginate(20)` and again to compute `max_price`. **Fix:** use the Scout-Plus builder's `aggregate(...)` to get both from one ES round-trip.
- **H-P3. `CommunityPolicy::isCurator` materializes the whole curators collection.** `app/Policies/CommunityPolicy.php:18` — `$community->curators->contains('id', $user->id)`. Called by `curator`, `update`, `preview`, `removeSelf` — many times per page. A community with 30 curators hydrates 30 User models per check. **Fix:** `$community->curators()->whereKey($user->id)->exists()`.
- **H-P4. `CommunityController::listings` and `show` are N+1 per shelf.** `app/Http/Controllers/Curated/CommunityController.php:64-66, 130-141` — each shelf triggers its own `posts()->paginate(8)` and `limitedCards` eager-load. A community with 12 shelves = 24+ extra queries. `listings()` is worse because it never paginates the outer shelf list. **Fix:** eager-load `shelves.posts.limitedCards` once, paginate in memory or per-shelf in a single query.

### Backend correctness

- ~~**H-Q1. `ConversationsController::notifyReceiver` never sends notification emails.**~~ **FIXED** — `$wasCaughtUp` now captured before the update; two regression tests added. (`ConversationsController.php:174-203`, `ConversationsControllerTest.php`)
- ~~**H-Q2. Exception messages leaked in 500 JSON responses.**~~ **FIXED** — dropped the `error` key in all 8 responses (HostEventController × 2, OrganizerController × 4, CommunityController × 1, AdminEventController × 1). `Log::error()` calls preserved so Sentry/logs still get the trace.
- ~~**H-Q3. `HostEventController::restore()` references undefined `$slug`.**~~ **FIXED** — method deleted. Verified zero references in `routes/`, `app/`, or `resources/js/` before removal.
- ~~**H-Q4. H7 fix incomplete — `AdminEventController::show` still uses the old click pattern.**~~ **FIXED** — `show()` now uses `loadCount` with the same `total_clicks`/`unique_visitors` pattern as `index()`. (`AdminEventController.php:79-104`)

### Migration hygiene

- ~~**H-M1. Duplicate `add_showtype_config_to_events_table` migration filenames.**~~ **FIXED** — `2025_11_07_222950_add_showtype_config_to_events_table.php` → `…_add_start_date_to_events_table.php` (`git mv`). Local `migrations` table updated. **Prod requires the same SQL update before next deploy — see post-deploy checklist above.**

### Frontend

- ~~**H-F1. Native `fetch()` in `location.vue:534` bypasses the axios timeout.**~~ **FIXED** — `AbortController` with 8s timeout. (`location.vue:528-552`)

---

## Medium

### Security

- **M-S1. `/login/code` has no IP-level throttle.** `routes/auth.php:20-26` — controller rate-limits by email only (5/hr per email). An attacker can POST thousands of `email: random+N@x.com` and each spawns a new `User` row + sends an email (Mailgun/Mailtrap bill spike, DB bloat, abuse vector). **Fix:** `->middleware('throttle:10,1')` per IP on `/login/code` and `/login/verify`.
- **M-S2. Click tracking accepts arbitrary `destination_url` / `click_type`.** `app/Http/Controllers/Creation/EventClickController.php:45-46` stores both unvalidated. If admin UI ever renders `destination_url` as a clickable link, `javascript:alert(1)` becomes admin XSS. **Fix:** `$request->validate(['destination_url' => 'nullable|url|max:255', 'click_type' => 'nullable|string|in:link,ticket,website,organizer'])`.
- **M-S3. `orderBy($userInput)` in `AdminAdvisoryController`.** `app/Http/Controllers/Admin/AdminAdvisoryController.php:94-103` — `sort_field` flows straight into Eloquent's `orderBy()`. Column names aren't bound; a moderator passing `sort_field=name -- ` produces SQL errors that leak schema. **Fix:** allow-list (`in_array(..., ['name','rank','created_at'])`) for both column and direction.
- **M-S4. `User::$fillable` includes `type`.** `app/Models/User.php:31`. No current path mass-assigns it, but it's a footgun — the next endpoint that does `$user->update($request->all())` becomes an instant privilege-escalation. **Fix:** remove from `$fillable`; require `forceFill(['type' => …])->save()` for admin code that needs it.
- **M-S5. `SESSION_SECURE_COOKIE` has no default.** `config/session.php:173` is `env('SESSION_SECURE_COOKIE')` → null/false if not set. Production almost certainly sets it, but defense-in-depth: `'secure' => env('SESSION_SECURE_COOKIE', app()->environment('production'))`.
- **M-S6. `getGravatar()` makes a blocking `get_headers()` call.** `app/Models/User.php:411-415`. Not user-controlled today, but inline HTTP from a model is a perf+SSRF footgun. **Fix:** move to a queued job, never call inline.

### Performance

- **M-P1. M12 still open — `EventAttributesController` endpoints uncached.** `app/Http/Controllers/Search/EventAttributesController.php:24-152` — 8 endpoints all hit the DB per request. The existing `Cache::forget('active-categories'/'active-genres')` calls in admin imply the cache key was planned. **Fix:** wrap each `get()` in `Cache::remember('event-attrs-<key>', 3600, …)`.
- **M-P2. Sentry browser SDK is the heaviest chunk (240 KB gz).** `public/build/assets/index-DI-gsyKs.js`. Ships full default integrations including browser-tracing and replay. **Fix:** explicit minimal integrations list in `resources/js/app.js`'s `Sentry.init`; drop `replayIntegration` if unused.
- **M-P3. Synchronous image processing blocks upload requests.** `app/Services/ImageHandler.php:16-79` runs 4 encodes + 4 DO Spaces PUTs sequentially. `AdminCategoryController::store:53-65` loops `saveImage` per file. A 5-image upload blocks 8-20s and can hit `max_execution_time`. **Fix:** queue a `ProcessImageUpload` job, return immediately with "processing" status.
- **M-P4. `Dock::with(['…' => fn($q) => $q->limit(4)])` is wrong.** `app/Http/Controllers/IndexController.php:18-50` and `app/Http/Controllers/Admin/AdminDocksController.php:27-65, 155-195`. The `limit(4)` applies to the *combined* result, not per-parent. With 2 docks × 10 cards each, only 4 cards total return. Functional bug. **Fix:** install `staudenmeir/eloquent-eager-limit` or use a window-function subquery.
- **M-P5. `Event::getMostExpensive()` is a full-table hydrate.** `app/Models/Event.php:418-427` loads every published event + all price ranges into memory just to `max()`. **Fix:** `PriceRange::whereHas('event', …)->max('price')` — one SQL query.
- **M-P6. `events-search.vue` fires an empty-query API call on every nav mount.** `resources/js/PageComponents/Nav/Components/events-search.vue:91-93`. Wasted ES query on every page load. **Fix:** require `searchInput.value.length >= 2` before calling.
- **M-P7. Hidden `moment` dependency.** 4 files (`card.vue:55`, `card-edit.vue:432`, `block-event.vue:82`, `album.vue:108`) `import moment from 'moment'` but `moment` is only in `package.json` transitively via `moment-timezone`. One upstream change breaks the build. **Fix:** either standardize on `dayjs` (already used in 5 files) or add `moment` explicitly.
- **M-P8. 8 unused npm dependencies.** `vue-cal`, `vue-slicksort`, `vue-flatpickr-component`, `vue-datepicker-next`, `vue3-dayjs-plugin`, `vue-advanced-cropper`, legacy `vuelidate@0.7`, `vue-draggable-next`. Verified: zero grep hits in `resources/js`. **Fix:** `npm uninstall` the list, run a build, ship.
- **M-P9. `VueDatePicker` registered twice.** `resources/js/app.js:84-90, 150` registers it globally as async; 7 child components also import it directly. Templates use `<VueDatePicker>` (PascalCase) which resolves to the local import first → global registration is dead weight. **Fix:** delete the global registration in `app.js`.

### Frontend correctness

- **M-F1. Memory leak — module-scope `window.addEventListener` in `show-gallery.vue:70`.** Runs at import time, not in `onMounted`, and is never removed. The handler is also `alert('Sharing options will appear here!')` — a placeholder shipped to production. **Fix:** move into `onMounted` + `onUnmounted` cleanup; replace the alert with `navigator.clipboard.writeText(url)` toast or a real modal.
- **M-F2. Memory leak — resize listener never removed.** `resources/js/PageComponents/Creation/Core/edit.vue:421` adds `resize` in `onMounted` with no `onBeforeUnmount` removal. **Fix:** add the cleanup.
- **M-F3. Memory leak — TikTok message listener + window globals.** `resources/js/PageComponents/Creation/Core/Pages/videos.vue:441` sets `window._tiktokPlayerListenerSet` and `window.controlTikTokPlayer`, registers a `message` listener that lives forever. **Fix:** remove listener + delete window globals in `onBeforeUnmount`, or use `AbortController` with `{signal}`.
- **M-F4. Race condition in nav search-as-you-type.** `resources/js/PageComponents/Nav/Components/events-search.vue:68` (and mobile + `Curated/Posts/event-search.vue:73`) — same pattern as H2 but in the nav. Late responses overwrite newer ones in the dropdown. **Fix:** `AbortController` per request, same pattern as `Search/all.vue`.
- **M-F5. `MapStore.subscribers` is an array.** `resources/js/Stores/MapStore.vue:15, 58`. The M7 fix to `SearchStore` (`Set` for dedup) was never applied to `MapStore`. Same bug. **Fix:** convert to `Set`.
- **M-F6. Login form not wrapped in `<form>`.** `resources/js/Auth/login.vue:56-84` — the email input is not inside a `<form @submit.prevent="sendCode">`. Pressing Enter does nothing, password managers can't autosubmit, the `required` attribute is bypassed. **Fix:** wrap in `<form>`, change button to `type="submit"`.
- **M-F7. Modal a11y missing app-wide.** Only the date pickers got `role="dialog"` / `aria-modal` (L4). Modals in `GlobalComponents/Modals/archive.vue:2`, `Admin/Management/Events.vue:257`, `Creation/index.vue:77`, `Curated/Communities/listings.vue:113` are missing all of: role, aria-modal, aria-label on close, Escape-to-close, focus trap. **Fix:** build a `BaseModal` wrapper component, migrate sites to it.

### Backend code quality

- **M-Q1. `SimilarEventsController` swallows all exceptions.** 5 catch blocks (`:71, 103, 126, 168, 209`) return `collect([])` and log only the message. Users see "no similar events" instead of any failure indicator; developers see log lines without event_id context. **Fix:** re-raise in non-prod, log with `['event_id' => $event->id, 'user_id' => auth()->id()]`.
- **M-Q2. `HostEventController::update` is 280 lines of mixed concerns.** `:56-332` does 14 distinct things (duplicate-name guard, attendance type, location, remote locations, timezone, shows, advisories, tickets, image up/delete/reorder, contact level, interactive level, age limit, videos, CTA, genres, cache invalidation). CLAUDE.md says the project uses Action classes. **Fix:** extract `UpdateEventAction` orchestrating `UpdateLocation`, `SyncShows`, `SyncImages`, `SyncAdvisories` sub-actions. Controller drops to ~30 lines.
- **M-Q3. `CommunityController::update` mixes curator-sync, name-change, and field-update.** `app/Http/Controllers/Curated/CommunityController.php:157-257`. Three operations in one endpoint. **Fix:** use the existing `updateCurators` at `:383` from the frontend; reserve `update()` for field changes.
- **M-Q4. Status-code magic strings everywhere.** `'p'`, `'r'`, `'d'`, `'e'`, `'n'`, `'0'`, `'1'` (statuses) and `'s'`, `'o'`, `'a'`, `'l'` (showtypes) in 15+ files. No central source of truth. Typos compile silently. The project already uses `Message::MESSAGES['APPROVED']`-style constants, so the pattern is accepted. **Fix:** `app/Enums/EventStatus.php` and `EventShowType.php` as PHP 8.2 backed enums.
- **M-Q5. `rejection_reason` silently dropped.** Already noted in prior audit, still open. `AdminEventController::reject:223-227` and `AdminOrganizerController::reject:141-144` both write `'rejection_reason' => $validated['reason']` to models where the column doesn't exist and isn't in `$fillable`. Reason only lives in the email + in-app message. **Fix:** add the column (and to `$fillable`) — the value is useful for admin history — or remove from the update array.
- **M-Q6. `User` has 4 redundant admin/moderator/curator checks.** `app/Models/User.php:83-86, 302-322, 383-406, 423-440` — method, getter accessor, inline `$this->type === 'a'`, `forClientSide()` inline. Edit one, easy to forget the others. **Fix:** keep the methods, delete the getter accessors.
- **M-Q7. `ConversationsController::update` has a message-duplication bug and missing validation.** Line 98: `"<p>" . $sanitizedMessage . "<br>" . $sanitizedMessage . "</p>"` — the new message text appears twice. Whole `update()` method never validates `message` (`required|string|max:5000`). **Fix:** correct the string composition and add validation.
- **M-Q8. `ConversationsController::update` returns unbounded messages.** `:114` returns `fresh()->load(['messages' => fn($q) => $q->orderBy('created_at', 'asc')])` with no `limit(100)` like `show()` has. Long conversations bloat the response. **Fix:** match `show()` — `limit(100)`.
- **M-Q9. Schedule registration uncertain.** `bootstrap/app.php:66-69` registers commands but I added the new `ei:archive-clicks` schedule to `ScheduleServiceProvider.php`. Verify on the next deploy: `php artisan schedule:list` should show all 3 EI commands (publish-embargoed, check-closing-events, archive-clicks).
- **M-Q10. `ei:publish-embargoed` has no per-iteration error handling.** `app/Console/Commands/PublishEventsCommand.php:55-86` — a single bad timezone string throws and stops the whole loop. **Fix:** try/catch each iteration, log & continue.

---

## Low

- **L1. `stripHtml` via detached div can fire `<img onerror>`.** `Admin/Management/Docks.vue:989`, `Curated/Posts/Cards/card-edit.vue:699`. Setting `innerHTML` on a detached div doesn't run `<script>` but DOES request `<img>` and `<iframe>`. Admin-trust mitigates. **Fix:** `DOMPurify.sanitize(html, { ALLOWED_TAGS: [] })`.
- **L2. `similar-events.vue` silent catch.** `:265-273` — server-side failure → console.error, no Sentry capture, fallback to filter over possibly-empty `window.allEvents`. **Fix:** `window.Sentry?.captureException(err)` in the catch.
- **L3. 22 `!important` overrides** in `show-purchase.vue:263-330` and `show-map.vue:157-186`. Fighting vue-datepicker and leaflet defaults. Brittle on library upgrades. **Fix:** `:deep()` selectors instead.
- **L4. Sentry init may overwrite custom errorHandler.** `app.js:122-142` — custom `app.config.errorHandler` set first, then `Sentry.init({ app })` runs after dynamic import. Verify in `@sentry/vue@10.53.1` that the custom console.error still fires after Sentry installs its own handler. **Fix:** test with a thrown component error, or move logging into Sentry's `beforeSend`.
- **L5. `vue-slider-component/theme/antd.css` imported in 3 files.** `Nav/Components/filters.vue:352`, `filters-mobile.vue:322`, `price.vue:63`. Duplicates the CSS across 3 chunks. **Fix:** import once in `app.css`.
- **L6. `Show::saveShows` hydrates each show to delete it.** `app/Models/Events/Show.php:63-77` — `each(fn ($show) => { $show->tickets()->delete(); $show->delete(); })`. For 60 dates that's 120+ queries on every save. **Fix:** `Ticket::whereIn('ticket_id', $event->shows()->pluck('id'))->where('ticket_type', Show::class)->delete(); $event->shows()->delete();` — 2 queries.
- **L7. Dead admin-mail loop in `NameChangeRequestService:41-44`.** `foreach ($admins as $admin) { /* Mail::to(...) commented out */ }` — admins are never notified, but `User::where('type', 'a')->get()` runs every request. **Fix:** delete the loop or uncomment the mail.
- **L8. `AdminDocksController` has a duplicated 40-line eager-load block.** `:25-67` and `:155-194` are identical. **Fix:** extract `getOrderedDocks()` helper.
- **L9. Frontend `v-for` lacks `:key` in several lists.** `Curated/Posts/event-search.vue:31`, `Curated/Communities/index.vue:35`, `show.vue:96`, `album-show.vue:7`, `grid-shelf.vue:10`. **Fix:** add `:key="item.id"`.

---

## Architectural / deferred (not "audit findings" — scheduled work)

- **M10 — date wizard `setTimeout` refactor needs splitting.** Documented in prior audit. The naive one-pass refactor silently loses user data on mode switches because `always-dates.vue` emits nothing and `ongoing-dates.vue` only emits dates. Proper path: **Pass A** = make children continuously emit full config up; **Pass B** = swap to `:initial-config` props and delete the `setTimeout`s. Manual smoke-test all 3×3 transitions between passes.
- **M13 — SoftDeletes cascade on Event.** Requires adding `SoftDeletes` (+ `deleted_at` migration) to `Show`, `Ticket`, `Image` first. Then a `static::deleting` hook on Event. Without that prep, naive cascade hard-deletes children and breaks restore.
- **H6 follow-up — FK constraints on `conversations.user_one`/`user_two`.** Columns are signed `bigInteger`; FKs need `unsignedBigInteger`. Requires `doctrine/dbal` or a raw SQL `MODIFY COLUMN`. Low urgency — the perf-impacting index is in place.
- **No `JsonResource` adoption — full models serialize on every response.** Every endpoint sends model + all appends + all loaded relations. The right fix is incremental: introduce `EventListResource`, `EventDetailResource`, `UserListResource` for the highest-traffic endpoints first.
- **No TypeScript.** 201 `.vue` + `.js` files, no `.ts`. Big migration, not urgent — better done opportunistically when refactoring big components.
- **`OrganizerPolicy::switchTeam` moderator superpower (H1).** Confirmed intentional. Documented in `OrganizerPolicy` docblock.

---

## Test coverage gaps (ranked by value-vs-effort)

Per the backend agent's analysis. Current state: 146 tests / 302 assertions / ~4s.

| Priority | Gap | Effort | Why |
|---|---|---|---|
| **High** | `ImageHandler` + the controllers that call it | 2-3 hr (one `Storage::fake('digitalocean')` setup unlocks 5+ follow-on tests) | 4 changes in 2025 alone, file-copy side effects outside transactions, zero disk-state asserts today. |
| **High** | `ei:publish-embargoed` command | 1 hr | Timezone arithmetic, already had bugs, runs every 2 hours in prod, completely untested. |
| **High** | `ListingsController::apiIndex` filter builder | 4-6 hr (refactor to extract `SearchFilterBuilder` class) | Highest-traffic endpoint. Don't try to test the ES round-trip; unit-test the query construction. |
| **High** | `AdminCommunityController` approval flow | 30 min | Copy-paste from existing event/organizer admin tests. |
| **Medium** | `Event::duplicate` image-copy side effects | 30 min | Already have happy-path coverage; just add `Storage::fake` assertions. |
| **Medium** | Curator invitation flow | 2-3 hr | Token expiry + email-mismatch logout is security-sensitive. |
| **Low** | Curated post/shelf/card CRUD | 4-6 hr | Less critical than core event flow; bigger surface area. |
| **Low** | `OrganizerPolicy::switchTeam` end-to-end (`current_team_id` write) | 20 min | Already partially covered. |
| **Low** | SocialAuth (Google/GitHub/Apple) | 3+ hr with Socialite mocking | High blast radius but rare changes; provider SDK does the heavy lifting. |
| **N/A** | Frontend M11 reconstruction (ongoing-dates.vue) | — | JS-side, can't reach from Pest. Add to a vitest backlog if/when frontend testing is set up. |

---

## Quick wins under 1 hour each (do-now shortlist)

If you have an hour and want maximum impact:

1. **CR1** — gate event-status mass-assignment. (security)
2. **CR2** — admin-gate the user-type field. (security)
3. **CR3** — `git rm --cached .env.local`, rotate secrets, add gitleaks. (security)
4. **H-Q1** — fix `notifyReceiver` unread bug. (real user impact: notification emails)
5. **H-S5** — sanitize `card-edit.vue:380` v-html.
6. **H-Q4** — `withCount` for `AdminEventController::show` (finish H7).
7. **H-Q2** — drop `$e->getMessage()` leaks from 7 catch blocks.
8. **M-S1** — IP throttle on `/login/code` and `/login/verify`.
9. **M-S3** — `orderBy` allow-list in `AdminAdvisoryController`.
10. **M-P1** — cache `EventAttributesController` endpoints (the long-open M12).
11. **M-P8** — `npm uninstall` the 8 unused deps.
12. **M-F5** — `MapStore.subscribers` to `Set`.
13. **M-F6** — wrap login in `<form>`.
14. **H-M1** — rename the misleading 2025_11_07 migration filename.

That's a ~3-4 hour batch that closes every Critical, half the Highs, and several Mediums.
