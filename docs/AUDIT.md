# EI Codebase Audit

**Last verified:** 2026-05-24
**Supersedes:** `CODE_REVIEW_ISSUES.md` (Apr 2026), `docs/AUDIT_REPORT.md` (Oct 2025), `docs/DATES_AUDIT.md` (Oct 2025), `docs/Claude_Audit.md` (Oct 2025)

**Recently fixed (2026-05-24):**
- All 3 Critical items (C1 blurb XSS, C2 message XSS, C3 test coverage foundation).
- All 12 High items (H1–H12) plus H2 pagination races. See "Already Fixed" at bottom for details.
- Sentry wired (`sentry/sentry-laravel` + `@sentry/vue`); DSNs already in `.env.prod`; production frontend DSN set as `VITE_SENTRY_DSN` GitHub secret.
- Two prod bugs from `laravel.log` fixed: TikTok URL truncation (`videos.url` widened to TEXT) and HostController null organizer crash.

Every finding below has been re-verified against the current code. Items resolved by prior work are listed in "Already Fixed" at the bottom so the historical record is preserved without cluttering the actionable list.

---

## Post-deploy verification checklist

Run through this after each production deploy that includes any of the fixes below. Each item lists what to do, what success looks like, and where to look if something is off.

### Pending — to verify on next prod deploy

- [ ] **TikTok URL widening (`videos.url` → TEXT)**
  - Migration: `2026_05_24_161932_widen_videos_url_to_text.php`. Workflow runs `php artisan migrate --force` automatically.
  - Verify on server: `mysql -uroot ei -e "SHOW COLUMNS FROM videos LIKE 'url'"` — `Type` should be `text`, not `varchar(255)`.
  - Live test: edit any event in admin, add a recent TikTok share link (the long one with `?utm_campaign=&utm_source=...`). Should save without error.
  - Roll back: `php artisan migrate:rollback --step=1` on the server.

- [ ] **HostController null-organizer fix**
  - Hard to trigger on purpose (needs a stale `current_team_id`). Smoke test: visit `/hosting` while logged in as a normal user. Page should load with your organizer dashboard.
  - Watch Sentry's Laravel project for the next 24h — should see zero `Attempt to read property "status" on null` errors. If any appear, the fallback path isn't catching them.

- [ ] **Sentry — backend project**
  - Verify on server: `php -r "require '/var/www/ei/vendor/autoload.php'; require '/var/www/ei/bootstrap/app.php'; echo config('sentry.dsn');"` — should print the DSN.
  - Live test: temporarily add `Route::get('/sentry-test', fn() => throw new \Exception('Sentry smoke'));` to `routes/web.php`, deploy, hit the URL, then **delete the route and re-deploy**.
  - Success: the exception appears in your Laravel Sentry project's "Issues" tab within ~30 seconds, with stack trace and request context.

- [ ] **Sentry — frontend Vue project**
  - Verify in browser DevTools console after the next deploy: `import.meta.env.VITE_SENTRY_DSN` should be baked into the bundle. Quick check: open any page, run `window.__SENTRY__` in the console — should show the Sentry hub object.
  - Live test: in browser console, run `setTimeout(() => { throw new Error('frontend sentry smoke'); }, 0);`
  - Success: error appears in the Vue Sentry project within ~30 seconds.
  - If nothing arrives: the build step probably didn't pick up the secret — check the latest GitHub Actions deploy log for the "Build assets (production)" step and confirm `VITE_SENTRY_DSN` was set.

- [ ] **C1 — Curated blurb sanitization**
  - Live test (happy path): visit any curated post that has rich-text formatting in a card blurb. Bold/italic/links/headings should all still render correctly.
  - Live test (attack path): edit a card and put `<img src=x onerror=alert(1)>` into the blurb. Save, then view the post. No alert should fire; the tag should be stripped or escaped.
  - Roll back: revert the commits touching `card.vue`, `nav.vue`, `card-edit.vue`, `curated/posts/show.blade.php`, `curated/primary.blade.php`.

- [ ] **C2 — Message sanitization**
  - Live test (happy path): send a message in the inbox with multiple lines. The line breaks should display as separate lines (the backend wraps with `<br>`).
  - Live test (attack path): send a message containing `<script>alert(1)</script>`. The message should appear as literal text or be stripped — no popup.
  - Email test: trigger a message that emails the recipient; the email body should not include raw `<script>` tags.

- [ ] **H4 — Conversation unique-index migration**
  - `2026_05_24_163439_add_unique_index_to_conversations_table.php` does a pre-flight duplicate check and will abort the deploy with a clear error if duplicate `(conversable_type, conversable_id, user_one, user_two)` groups already exist in prod.
  - If aborts: SSH in, run `SELECT conversable_type, conversable_id, user_one, user_two, COUNT(*) c FROM conversations WHERE user_one IS NOT NULL AND user_two IS NOT NULL GROUP BY 1,2,3,4 HAVING c > 1;` — dedupe (keep oldest, merge messages), then redeploy.
  - Verify after: `SHOW INDEX FROM conversations WHERE Key_name = 'conversations_participants_unique';` returns a row.

- [ ] **H7 — Click-stats query**
  - Visit `/admin/manage/events` after deploy. Page should load noticeably faster than before, especially for events with thousands of clicks. `total_clicks` and `unique_visitors` columns should still show correct numbers.

- [ ] **H8 — Click archival cron**
  - Cron runs daily at 03:30. After the first run, check `storage/logs/archive-clicks.log` for a "Deleted N click rows older than YYYY-MM-DD." message.
  - If you want to test immediately: SSH and run `php artisan ei:archive-clicks --days=999999` (will report zero deletions — confirms the command works).

- [ ] **H12 — Vue error handler**
  - In browser console on prod: `setTimeout(() => { throw new Error('vue error handler smoke'); }, 0);` — should see `[Vue] ... Error: vue error handler smoke` in console AND appear in Sentry Vue project. (Combine with the Sentry Vue smoke test above.)

### Recurring checks (run periodically)

- [ ] **Sentry issues triage** — at least weekly, open the Issues tab in both Sentry projects. Anything new and recurring is real-world evidence of bugs the audit doesn't know about.
- [ ] **`storage/logs/laravel.log` on the server** — until Sentry is the primary signal, still worth `tail -100 /var/www/ei/storage/logs/laravel.log | grep -E "ERROR|CRITICAL"` after a deploy.

### How to roll back any of the above

The deploy workflow does not auto-rollback. If a production deploy breaks something:
1. Find the last good commit on `main`.
2. Manually trigger the workflow (`workflow_dispatch` → `production`) against that commit, OR `git revert <bad-commit> && git push`.
3. If a migration is the problem, SSH in: `cd /var/www/ei && php artisan migrate:rollback --step=N` *before* re-deploying.

---

## Critical

_All three Critical items were fixed on 2026-05-24 — see notes at the top. Remaining test coverage gap is now under "High" since the foundational suite + auth coverage exists._

---

## High

_H1–H12 plus H2 were fixed on 2026-05-24 — see "Already Fixed" at bottom. Only H13 remains as ongoing work._

### H13. Test coverage on business paths
- **Now covered:** magic-link auth (`LoginCodeTest`), API auth gates (`AuthGateTest`), event attributes (`EventAttributesTest`), organizer name check (`OrganizerCheckNameTest`), `EventPolicy` + `OrganizerPolicy` + `CommunityPolicy` in full, click tracking + stats (`EventClickControllerTest`), HostEventController submit/destroy/create/update/duplicate (`HostEventControllerTest`), admin approval workflow for events and organizers (`AdminEventControllerTest`, `AdminOrganizerControllerTest`), and the conversation/messaging flow with regression coverage for the H5 null-guard fix (`ConversationsControllerTest`). **144 tests / 294 assertions / ~4s.**
- **Still uncovered:** search (`ListingsController` — needs Scout/ES test infra), image uploads (`ImageHandler` — needs `Storage::fake('do')`), admin community approval (`AdminCommunityController`), scheduled commands (`ei:publish-embargoed`, `ei:check-closing-events`, `ei:archive-clicks`), `PostPolicy` and `ConversationPolicy` (already partially exercised via ConversationsControllerTest), curated posts/shelves/cards CRUD, and the curator-invitation flow.
- Live findings while writing tests:
  - `AdminEventController::reject` silently drops `rejection_reason` (column doesn't exist + not in `$fillable`). Reason only appears in the email and in-app message, not on the event.
  - `CommunityPolicy::curator` requires pivot membership — owner alone is not enough. Same pattern as `EventPolicy::host`.
- Fix incrementally as you touch each area; the suite + factories now exist as a foundation.

### H6 follow-up. FK constraints on `conversations.user_one/user_two`
- The composite index was added 2026-05-24, but FK constraints to `users.id` were deferred — the columns are `bigInteger` (signed) and FKs require `unsignedBigInteger` to match. Adding FKs needs a column-type change, which requires `doctrine/dbal` or a careful raw SQL `MODIFY COLUMN` migration after verifying no negative values exist.
- Low urgency now that the perf-impacting index is in place.

---

## Medium

### M1. Weak 6-digit login codes
- `app/Http/Controllers/Auth/LoginCodeController.php:41` — `random_int(0, 999999)`. Mitigated by 10 verify-attempts / 15-minute rate limit and 15-minute TTL, so practical brute force is bounded but not impossible.
- Optional fix: 8-character alphanumeric, or move to magic-link URL only.

### M2. Unsafe auto-login email parameter
- `app/Http/Controllers/Auth/LoginCodeController.php:128-139` — `autoLogin($code)` reads `request()->query('email')` with no validation and flashes it. The eventual verify call still requires the cached code to match the email, so this is a UX-disclosure issue, not auth bypass.
- Fix: `request()->validate(['email' => 'required|email'])` before flashing.

### M3. CSRF token fetched without null-check
- `resources/js/Auth/login.vue:212` does `document.querySelector('meta[name="csrf-token"]').content`. If the meta tag is ever missing this throws and silently disables the login path.
- Fix: `const meta = document.querySelector(...); if (!meta) { /* log + show error */ }`.

### M4. Organizer name lookup discloses full record
- `app/Http/Controllers/OrganizerController.php:275-298` returns `id, name, slug, description` for name matches. Rate-limited (`routes/api.php:55-58`, `throttle:60,1`) but still allows enumeration of organizer descriptions.
- Fix: return only `slug` (or a boolean) — the UI only needs a "this name is taken" signal.

### M5. CORS `allowed_methods` and `allowed_headers` are `*`
- `config/cors.php:20, 26`. Origin is restricted to `FRONTEND_URL`, so this is hardening, not an active hole.
- Fix: explicit method/header allow-lists.

### M6. No axios timeout
- `resources/js/app.js:119-121` — no `axios.defaults.timeout`. Requests can hang forever.
- Fix: `axios.defaults.timeout = 30000;`

### M7. SearchStore listener accumulation
- `resources/js/Stores/SearchStore.vue:90-96` — `subscribe()` pushes callbacks without dedupe. Most callers do unsubscribe in `onUnmounted`, but a re-mount before unmount duplicates listeners.
- Fix: store listeners in a `Set` or check existence before pushing.

### M8. Frontend/backend "6 months from now" mismatch
- `resources/js/PageComponents/Creation/Core/Pages/Dates/ongoing-dates.vue:1027` uses `setMonth(getMonth()+6)` from `now` and ignores timezone.
- `app/Models/Events/Show.php:234, 239, 250` uses `Carbon::now($timezone)->addMonths(6)`.
- These can differ by hours/days near month boundaries.
- Fix: route the calculation through the existing `addMonths(effectiveStartDate, 6, timezone)` helper that the rest of the file already uses (lines 660, 724).

### M9. Image dimension validation removed entirely
- `app/Http/Requests/StoreEventRequest.php:69-76` — comment: `// Removed dimensions validation as it seems to cause issues`. Any 50×50 or 8000×6000 image is accepted and reshaped by `ImageHandler::cover()`, producing blurry uploads or CPU spikes.
- Fix: re-add `'dimensions:min_width=800,min_height=600'` with a friendly error message; no max needed since the backend downscales.

### M10. Ongoing-dates state preservation uses `setTimeout`
- `resources/js/PageComponents/Creation/Core/Pages/dates.vue:216-271` switches showtype, then uses `setTimeout(..., 100)` to push state into child refs. Timing-fragile.
- Fix: drive child config via reactive props instead of imperatively poking refs.

### M11. Ongoing-dates pattern reconstruction is heuristic
- `resources/js/PageComponents/Creation/Core/Pages/Dates/ongoing-dates.vue:1000-1047` — counts day-of-week frequency and treats anything ≥2 as "part of the pattern". Editing an existing event can drop legitimate weekdays.
- Fix: persist `showtype_config` (JSON) on `events` with the actual chosen weekdays + start/end so reconstruction is exact.

### M12. Event attributes endpoints never cached
- `app/Http/Controllers/Search/EventAttributesController.php` — `categories()`, `genres()`, `remoteLocations()`, `contactLevels()`, `interactiveLevels()`, `contentAdvisories()`, `mobilityAdvisories()`, `ageLimits()` all hit the DB on every request. `AdminEventController::approve()` already does `Cache::forget('active-categories')` / `'active-genres'`, indicating the cache key convention exists but is unused here.
- Fix: wrap each with `Cache::remember('event-attrs-<key>', 3600, fn() => …)`.

### M13. SoftDeletes don't cascade to shows/tickets/images
- `app/Models/Event.php:41-44` only registers `PublishedScope`. Soft-deleting an event leaves its shows, tickets, and image rows visible.
- Fix: register `static::deleting` to soft-delete the relations, or adopt a cascade trait.

### M14. Validation helper exists but is never called
- `resources/js/composables/dateUtils.js:214-216` exports `isValidTimezone()`. The actual `normalizeDateToTimezone()` at lines 18-26 never calls it; `moment.tz(date, badZone)` silently coerces to UTC.
- Fix: have `normalizeDateToTimezone` call `isValidTimezone` first and log/warn.

### M15. `name-change` endpoint missing throttle
- `POST /organizers/{organizer}/name-change` lives in `routes/web.php:67` and gets default web middleware only — every other public API endpoint has an explicit `throttle:N,M`.
- Fix: add `->middleware('throttle:5,60')` (5 per hour is plenty).

---

## Low

### L1. Silent map init failure
- `resources/js/PageComponents/Search/Components/map.vue:212-281` calls `L.map(...)`, `L.tileLayer(...)`, `L.markerClusterGroup(...)` with no error handling. Leaflet load failures show a blank box.
- Fix: try/catch around init with a fallback "map unavailable" state.

### L2. `Date.now()` IDs in dropdown
- `resources/js/GlobalComponents/dropdown.vue:124` — `id: Date.now()`. Two adds in the same millisecond collide and break `:key` tracking.
- Fix: `crypto.randomUUID()` or an incrementing counter.

### L3. Console.log noise in source
- 91 `console.log` calls across `resources/js`. Stripped by `vite.config.js:33-40` (`drop_console: isProduction`) in production builds, so this is style/leak hygiene, not a runtime issue. Worth cleaning during touched-file work.

### L4. Date picker / calendar a11y
- No `aria-label`, `role="dialog"`, or `aria-modal` in `resources/js/PageComponents/Creation/Core/Pages/Dates/*`.
- Fix incrementally — these components are due for a UX pass anyway.

### L5. Dead password auth controllers
- App uses magic-link only, but `app/Http/Controllers/Auth/NewPasswordController.php` and `PasswordResetLinkController.php` still exist. Confirm they're not wired in `routes/auth.php` then delete.

---

## Already Fixed (verified, kept for the historical record)

### Critical
- **C1** blurb HTML sanitized via DOMPurify in Vue (`composables/useSanitize.js`) and `Purifier::clean(..., 'blurb')` in Blade.
- **C2** messaging sanitized to `<p>`/`<br>` only via DOMPurify and `Purifier::clean(..., 'message')` in email template.
- **C3** (foundation) default password-auth tests removed, `LoginCodeTest` + smoke tests added (28 tests passing). `phpunit.xml` configured to use a separate `ei_testing` MySQL DB so `RefreshDatabase` doesn't touch local prod-copy data. `SCOUT_DRIVER=null` in tests.

### High (fixed 2026-05-24)
- **H1** `OrganizerPolicy::switchTeam` — confirmed intentional moderator power; documented in policy.
- **H2** pagination races — `AbortController` added in `Search/all.vue` and `Search/location.vue`.
- **H3** double-submit on event creation — `isSubmitting` guard moved to top of `goToNext()`.
- **H4** race-condition conversations — unique index migration on `(conversable_type, conversable_id, user_one, user_two)` with duplicate pre-check.
- **H5** null crash in `canAppendMessage` — null guard added.
- **H6** missing index on `conversations` — `(user_one, user_two)` + `user_two` index migration added. FK constraints deferred (see H6 follow-up under High).
- **H7** click-stats query — `withCount('clicks as total_clicks')` + `withCount('clicks as unique_visitors' …distinct('ip_address'))`; in-memory loop removed.
- **H8** `track_clicks` unbounded — composite indexes added; `ei:archive-clicks` command added; scheduled daily at 03:30.
- **H9** N+1 in `belongsToOrganization()` — switched to `->teams()->whereKey()->exists()`.
- **H10** organizer event catalog — `->limit(12)` added to eager-loaded `organizer.events`.
- **H11** missing indexes on `events` — migration adds `organizer_id`, `category_id`, `archived`, `rank`, `published_at`, and composite `(status, organizer_id)`.
- **H12** global Vue error handler — `app.config.errorHandler` set; routes to Sentry when available, falls back to `console.error`.

### Sentry & infra
- Sentry SDKs (`sentry/sentry-laravel`, `@sentry/vue`) installed and wired in `bootstrap/app.php` + `resources/js/app.js`. DSNs in `.env.prod`; production frontend DSN passed via GitHub secret `VITE_SENTRY_DSN` to the Vite build step.

### Prod log fixes (from `laravel.log` review 2026-05-24)
- TikTok URL truncation: `videos.url` widened from `VARCHAR(255)` to `TEXT`.
- HostController null-organizer crash: switched to `User::getCurrentOrganizer()` with safe fallback + redirect.

### Pre-existing (verified during audit consolidation)
- Rate limiting on `LoginCodeController::sendCode` / `verify`.
- `auth:sanctum` + `can:manage,event` middleware on `POST /hosting/event/{event}`.
- Throttle middleware on `track-click`, `/index/search`, `/events/{event}/similar`, `/organizers/check-name`, `/search/nav/*`, and the authenticated hosting routes.
- `DEAD_LoginController.php` removed from `app/Http/Controllers/Auth/`.
- `Cache::forget` invalidation pattern for `active-categories` / `active-genres` in `AdminEventController::approve`.

## False Positives (verified, no action needed)

- "SQL injection in conversation search" (`ConversationsController.php:134, 159`). Despite the `"%$request->search%"` interpolation, the value is passed to Eloquent `where(..., 'LIKE', $value)` as a bound parameter. The `%` lives in the *value*, not the SQL.
- "XSS via `{!! $event->tag_line !!}`" in `resources/views/events/show.blade.php`. The tag line is rendered with safe `{{ }}` throughout that file.
- "Orphaned files on failed event duplication" (`app/Models/Event.php:520-602`). The code at `:577-580` documents the trade-off: DB records roll back, file copies may be left behind. Storage cleanup could be added, but the original "orphaned references" risk is already prevented.
