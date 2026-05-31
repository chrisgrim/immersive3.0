# EI Server Audit — Punchlist

Server: `64.23.181.106` (ei-2024-04, Ubuntu 24.04, 2 CPU / 3.8 GB)
Audit date: 2026-05-26

Work through these a few at a time. Strike each one as we do it.

---

## 🔴 Critical

- [x] ~~**1. Rotate Redis password**~~ — done 2026-05-26. New 40-char password set in `/etc/redis/redis.conf`, `/var/www/ei/.env`, `/var/www/secret/.env`. Redis restarted, Laravel config re-cached on both apps, `Redis::ping() → PONG`. New value saved to `/root/.redis_password` (mode 600) on server.

- [x] ~~**2. Fix CORS leaking dev origin into prod**~~ — done 2026-05-26. Root cause: prior M5 commit (May 24) tightened CORS methods/headers but assumed FRONTEND_URL was set in prod `.env`. It wasn't — fell back to default `http://localhost:3000`. Added `FRONTEND_URL=https://everythingimmersive.com` to prod, `FRONTEND_URL=https://dev.secretchickens.com` to dev. **Note**: ACAO appearing on every response (regardless of Origin) is intentional fruitcake/php-cors behavior for single-origin configs — browser-side CORS enforcement still blocks mismatched origins.

- [~] ~~**3. Disable root SSH login**~~ — **Will not do.** User has been locked out by this change in the past. Current state (root login, key-only, no password auth, fail2ban) is acceptable. See memory `feedback-no-disable-root-ssh`.

- [x] ~~**4. Apply pending updates + reboot**~~ — done 2026-05-26. `apt full-upgrade` (libgd3 + 4 kernel packages) and full reboot. Kernel: 6.8.0-71 → **6.8.0-117**, verified via `uname -r`. All services back, smoke tests green. Also: `Unattended-Upgrade::Automatic-Reboot "true";` + `Automatic-Reboot-Time "04:00";` enabled. **Lesson learned (saved as feedback memory)**: SSH-triggered reboots via `(sleep N && reboot) &` are unreliable — subshell gets SIGHUP'd on SSH disconnect. Use `systemd-run --on-active=5s /sbin/reboot` instead.

---

## 🟠 Medium — performance & hardening

- [x] ~~**5. Raise PHP-FPM workers**~~ — done 2026-05-26. All 5 settings applied to `/etc/php/8.4/fpm/pool.d/www.conf`, FPM restarted, 5 workers running, ceiling at 15.

- [x] ~~**6. Bump MySQL InnoDB buffer pool**~~ — done 2026-05-26. `innodb_buffer_pool_size=1G` confirmed, slow query log ON at `/var/log/mysql/slow.log` (1s threshold). MySQL restart took ~2s.

- [x] ~~**7. Enable OPcache JIT**~~ — done 2026-05-26. **Gotcha**: `/etc/php/8.4/mods-available/opcache.ini` had `opcache.jit=off` which is symlinked into `fpm/conf.d/10-opcache.ini` and overrides `php.ini`. Fixed in the mods-available file. JIT verified live (`jit.on=true`, `kind=5`, `opt_level=4`). Median response time dropped 10-13% across `/`, `/events`, `/communities`. **Left `opcache.validate_timestamps=1` alone for now** — flipping it to 0 needs a deploy-pipeline opcache flush step first.

- [x] ~~**8. Drop TLS 1.0/1.1**~~ — done 2026-05-26. `ssl_protocols TLSv1.2 TLSv1.3;` in `/etc/nginx/nginx.conf`. Verified: `openssl s_client -tls1_1` returns "no protocols available".

- [x] ~~**9. Add HSTS**~~ — done 2026-05-26. Added to all three 443 server blocks (EI apex + EI www-redirect + dev). Header confirmed live: `strict-transport-security: max-age=31536000; includeSubDomains`.

- [x] ~~**10. Align upload limits**~~ — done 2026-05-26. nginx `client_max_body_size 50M;` (both sites), PHP `upload_max_filesize=50M`, `post_max_size=55M`. Verified at runtime.

- [x] ~~**11. Decide on fastcgi_cache**~~ — done 2026-05-26. Chose Option A: deleted all 4 `fastcgi_cache*` directives from EI site config + the orphan `fastcgi_cache_path` in `nginx.conf`. Revisit (Option B with cookie-bypass guards) if anon traffic ever needs perf help.

- [x] ~~**12. Move fastcgi_cache_path off /tmp**~~ — done 2026-05-26. Moved to `/var/cache/nginx/fastcgi` (owned by www-data, 700). Old `/tmp/nginx_cache` is empty and can be left or `rmdir`-ed.

---

## 🟡 Low — cleanup

- [x] ~~**13. Pin Elasticsearch to 127.0.0.1**~~ — done 2026-05-26. Changed `http.host: 0.0.0.0` → `http.host: 127.0.0.1` in `/etc/elasticsearch/elasticsearch.yml`. `ss` now shows `127.0.0.1:9200`, external connection to `64.23.181.106:9200` refused. App search verified working (`/api/index/search` → 200).

- [x] ~~**14. LOG_LEVEL=debug in prod `.env`**~~ — done 2026-05-26. Set to `warning`, config re-cached, `config('logging.channels.single.level') = warning` confirmed.

- [x] ~~**15. Clean up `.env` duplicates**~~ — done 2026-05-26. Removed `BROADCAST_DRIVER=log` (line 31), `QUEUE_CONNECTION=sync` (line 32), `QUEUE_DRIVER=redis` (line 44). Verified Laravel still reads `queue.default=database`, `cache.default=redis`, `session.driver=redis`.

- [x] ~~**16. Remove default nginx site**~~ — done 2026-05-26. `nginx -t` passed, reloaded cleanly, both sites still HTTP 200.

- [x] ~~**17. Add fail2ban nginx jails**~~ — done 2026-05-26. Added 3 jails via `/etc/fail2ban/jail.local`: `nginx-botsearch` (WP/PMA probes), `nginx-bad-request` (malformed), `nginx-php-probes` (custom filter at `/etc/fail2ban/filter.d/nginx-php-probes.conf` for phpunit/eval-stdin/kcfinder/.env/.git). **Gotcha**: default `backend = systemd` doesn't see nginx access logs (those go to file, not journal). Had to set `backend = polling`. Retroactively banned 7 IPs on restart.

- [~] ~~**18. Fix Elasticsearch `published_at` mapping**~~ — **Not a real bug**. Live mapping is already `date` (verified via `/events/_mapping`). The 2 errors seen in logs were transient cutover artifacts at 18:30:11 today, with zero recurrence since. Past-you already fixed this in April 2025 commits 20b3e00 + 18afe73 (added explicit date mapping + `published_at_sort` keyword fallback). My original audit assumption was wrong.

- [~] ~~**19. Consider atomic deploys**~~ — **Will not do.** User declined; current in-place rsync is acceptable.
