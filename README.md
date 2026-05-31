# Everything Immersive (EI)

Event-discovery and community-curation platform for immersive experiences. Laravel 12 + Vue 3 SPA, deployed via GitHub Actions to a DigitalOcean droplet.

- **Live:** https://everythingimmersive.com
- **Stack:** Laravel 12 (PHP 8.2+, MySQL, Eloquent) · Vue 3 (`<script setup>`, Tailwind 3.4, Vite 5) · Elasticsearch (Laravel Scout) · Sanctum + Socialite (Google/GitHub/Apple) + passwordless email codes · **Redis** sessions · DigitalOcean Spaces for images

## Quick start

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate
npm run dev            # Vite HMR (ei.test:5173)
php artisan test       # Pest backend suite
npm run test:js        # Vitest frontend suite
./vendor/bin/pint      # format (PSR-12)
```

## Where to look

- **[`CLAUDE.md`](./CLAUDE.md)** — the primary dev/ops guide: architecture, domain model, conventions, **server access + deploy mechanics**, gotchas. Start here.
- **[`docs/`](./docs/README.md)** — open-issues audit, timezone + geo guides, and the test-suite findings log.

---

## Domain reference — status codes & types

### User Status (`type`)
- **C** = Curator · **M** = Moderator · **A** = Admin · **G** = Guest

### Post Type
- **s** = Normal post with featured image · **h** = Hidden featured image · **e** = Event featured image · **c** = Cards featured image

### Notification Type (`newsletter_type`)
- **n** = No newsletter or event updates · **a** = Yes newsletter + yes updates · **m** = Yes newsletter + no updates · **u** = No newsletter + yes updates

### Card Type
- **E** = Event · **T** = Text · **I** = Image · **H** = Event without Image

### Show Type
- **S** = Specific show dates · **L** = Limited Run · **O** = Ongoing all days · **A** = Always

### Event Status
- **D** = Draft · **E** = Embargo dated · **P** = Published · **R** = Ready for admin · **N** = Notes · **0** = New Event
- Wizard progress: **1** = Title · **2** = Location · **3** = Category · **4** = Dates · **5** = Tickets · **6** = Description · **7** = Advisories · **8** = Image

### User Reminder
- **0** = No organizer or event submitted · **1** = Organizer created but no event · **2** = Event created but not submitted
