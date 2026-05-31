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
- **Event status**: `d`=draft, `0`=new, `r`=rejected, `p`=published, `e`=embargoed, `n`=other
- **Event showtype**: `s`=specific dates, `o`=ongoing, `a`=always, `l`=limited
- **Content status** (organizer/community/post): `p`=published, `d`=draft, `r`=rejected

### Key Patterns
- **Action classes** for business logic instead of fat controllers
- **Global scopes** on models (PublishedScope, RankScope, DateScope, AdminScope)
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

### Server access
- **SSH**: `ssh root@64.23.181.106` (locally aliased as `sshei`; `id_rsa` is authorized, so it connects with no `-i` needed). Single DigitalOcean droplet hosting both targets: **prod** at `/var/www/ei`, **dev/staging** at `/var/www/secret`.
- The server `.env` is the source of truth for runtime config and is **excluded from the deploy rsync** — editing it then deploying (which runs `config:cache`) is what activates `.env` changes (and can flip the session store / log everyone out; see Sessions note above).

## Important Notes

- **NEVER `git push` or trigger remote deploys without explicit user permission.** Local commits are fine. `git push`, `gh workflow run`, and anything that hits CI/CD or prod requires an explicit "push" / "ship" / "deploy" from the user each time. This applies even if the previous push went green and the next change looks small. Don't auto-push between iterations during a review cycle.
- Events use `SoftDeletes` — always check for soft-deleted records
- Event search index only includes published events (`shouldBeSearchable()` checks `status === 'p'`)
- Image paths stored on models (`largeImagePath`, `thumbImagePath`) AND in polymorphic `images` table
- The `closingDate` on events determines visibility; shows have individual dates
- API rate limits vary by endpoint group (30-600 requests/min)
- Admin/moderator routes require `auth:sanctum` + `moderator` middleware
- Timezone handling is critical — events store their own timezone, embargo publishing respects it
