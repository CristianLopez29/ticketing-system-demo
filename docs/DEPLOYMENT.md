# Deploying to the VPS

The stack is Dockerised and shares the host with your other applications (`candidacy`,
`eventhub`), so it must not claim ports 80/443 for itself. It runs behind the **shared
Traefik proxy** that owns :80 and :443 for the whole box, joins that proxy's `edge`
network, and publishes no host port of its own.

`compose.yaml` in this repo is the Laravel Sail **development** runtime and is not used here.
Production is `compose.prod.yaml` plus `docker/production/`.

---

## Layout

```
                     :80/:443
                        |
          +-------------v--------------+
          |  traefik                   |   /srv/traefik - deployed once per host
          |  network: edge             |   terminates TLS for every app
          +--+---------+------------+--+
             |         |            |
      ticketing_web  candidacy     eventhub            <- no published ports
             |
   +---------v---------+
   |  network: internal (private to this stack)
   |  app (php-fpm) - worker - scheduler - mysql - redis
   +-------------------+
```

Traefik terminates TLS and speaks plain HTTP to `web` over `edge`. Only `web` joins `edge`;
MySQL and Redis stay on `internal` and are unreachable from the host, from the internet, and
from the other apps' containers.

| Container | Role |
|---|---|
| `app` | PHP-FPM 8.4, the application image |
| `web` | Nginx, same image build, carries its own copy of `public/` |
| `worker` | `queue:work` — **required**, payment is a saga; without it reservations never become tickets |
| `scheduler` | `schedule:work` — **required**, releases seats whose reservation expired |
| `mysql`, `redis` | Private to this stack, unreachable from the host or the other apps |

Every container, volume and the default network is namespaced by `name: ticketing` in
`compose.prod.yaml`, so nothing collides with the other stacks on the box.

## 1. One-time host setup

Traefik is **not** deployed from this repo. It is a host-level component installed once, in
its own directory (`/srv/traefik`), from the shared proxy stack kept alongside the `candidacy`
application (`deploy/traefik/` there). This repo only assumes it is already running and that
the `edge` network exists:

```bash
docker network ls | grep edge     # must exist before `up -d`
docker network create edge        # only if it does not
```

Three settings from that stack are hard requirements here, and all three are already baked
into `compose.prod.yaml`:

| Traefik setting | What this repo must match |
|---|---|
| `--providers.docker.network=edge` | the external network is named `edge`, not `proxy` |
| `--providers.docker.exposedbydefault=false` | `web` must carry `traefik.enable=true` or it is never routed |
| `--certificatesresolvers.letsencrypt...` | the cert resolver is named `letsencrypt` |

**`ufw` does not protect published container ports.** Docker writes its rules into the
`DOCKER` iptables chain, which is evaluated before ufw's, so a container publishing
`3306:3306` is reachable from the internet even while `ufw status` claims that port is denied.
That is the real reason no service in `compose.prod.yaml` has a `ports:` key. Verify it from
**another machine** after every deploy — only 22, 80 and 443 may be open:

```bash
nmap -Pn -p 22,80,443,3306,6379 <your-server-ip>
```

## 2. Configure

```bash
git clone https://github.com/CristianLopez29/ticketing-system-demo.git /srv/ticketing
cd /srv/ticketing

cp .env.production.example .env
$EDITOR .env          # fill every <placeholder>
```

Generate the secrets rather than inventing them:

```bash
docker run --rm php:8.4-cli php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'   # HEALTHCHECK_TOKEN
openssl rand -base64 32                                                        # DB / Redis passwords
```

### Environment variables that must be set

| Variable | Why it matters |
|---|---|
| `APP_KEY` | Encryption and signed URLs. Generated in step 3. |
| `APP_DEBUG=false` | With `true`, stack traces and server paths are returned to clients. |
| `APP_ENV=production` | Also what switches the Swagger UI to Basic auth. |
| `APP_URL` | Must be the public `https://` origin; used to build absolute URLs. |
| `APP_DOMAIN` | Bare hostname Traefik matches on. Compose interpolates it into the router rule, so an empty value produces a router that matches nothing. |
| `TRUSTED_PROXIES=*` | **Without it every per-IP rate limit collapses into one bucket.** `*` is safe *only* because no port is published — see below. |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `REDIS_PASSWORD` | The services are private to the stack, but a compromised sibling container on a shared network should still hit a password. |
| `LOG_CHANNEL=daily_json` | JSON lines with daily rotation, instead of one unbounded text file. |
| `DOCS_AUTH_PASSWORD` | Basic-auth password for `/api/documentation`. Unset means the route answers **503** — never open. |
| `SENTRY_LARAVEL_DSN` | Error monitoring. Leave empty to disable Sentry entirely. |
| `SESSION_SECURE_COOKIE=true` | The proxy terminates TLS, so this is correct. |
| `CORS_ALLOWED_ORIGINS` | `*` is only defensible while authentication stays bearer-token only. |

**Why `TRUSTED_PROXIES=*` is safe here.** `X-Forwarded-For` is attacker-controlled input.
Trusting any proxy is only sound when the client cannot reach the app directly, which is why
`compose.prod.yaml` declares no `ports:` on `web` — the sole route in is Traefik on the `edge`
network. If you ever publish a port for debugging, change this to `172.16.0.0/12` for as long
as that port is open.

## 3. First deploy

The `APP_DOMAIN` A record must already point at the VPS: Traefik requests the certificate on
the first matching request and repeated failures burn the Let's Encrypt rate limit. See
[step 4](#4-dns-and-tls).

```bash
docker compose -f compose.prod.yaml up -d --build

docker compose -f compose.prod.yaml exec app php artisan key:generate --force
docker compose -f compose.prod.yaml exec app php artisan migrate --force
docker compose -f compose.prod.yaml exec app php artisan l5-swagger:generate

# key:generate invalidated the config cached at container start
docker compose -f compose.prod.yaml restart app worker scheduler
```

## 4. DNS and TLS

The Traefik labels are already on the `web` service in `compose.prod.yaml`, driven by
`APP_DOMAIN`:

```yaml
        labels:
            traefik.enable: 'true'
            traefik.docker.network: edge
            traefik.http.routers.ticketing.rule: Host(`${APP_DOMAIN}`)
            traefik.http.routers.ticketing.entrypoints: websecure
            traefik.http.routers.ticketing.tls.certresolver: letsencrypt
            traefik.http.services.ticketing.loadbalancer.server.port: '80'
```

Nothing has to change on the Traefik side to add this app. Two constraints:

- **Point the `APP_DOMAIN` A record at the VPS before the first `up -d`.** Traefik uses the
  TLS-ALPN challenge, which fails while the record is missing, and repeated failures hit the
  Let's Encrypt rate limit for that domain.
- **`ticketing` must stay unique** across every app on the box. `traefik.http.routers.<name>`
  collides silently between compose projects — the second definition simply loses.

`traefik.docker.network` is not decoration: `web` sits on both `edge` and `internal`, and
without it Traefik may resolve the container to its `internal` address, which it cannot reach.

Traefik redirects :80 to :443 at the entrypoint, so `web`'s nginx only ever sees plain HTTP on
port 80 from the proxy. That is expected — `SESSION_SECURE_COOKIE=true` and the `https://`
URLs are recovered from `X-Forwarded-Proto`, which is exactly what `TRUSTED_PROXIES` governs.

### Rate limiting: three layers, on purpose

| Layer | Scope | What it stops |
|---|---|---|
| Application (`throttle:` middleware) | Per user, per email, per IP | Business limits: 5 login attempts/min per email, 60 purchases/min per user, 60/min on public reads |
| Container Nginx (`limit_req`, 30 r/s) | Per IP, before PHP | Volumetric abuse; still answers when PHP-FPM has no free workers |
| Traefik `flood-guard@file` (50 r/s, burst 100) | Per client IP, every app on the box | A flood that would otherwise reach PHP at all |

The application limits cannot be expressed in Nginx — it knows nothing about users or emails —
and the Nginx limit cannot be expressed in the application, which runs too late to help.

**`flood-guard` applies to the k6 stress test too.** It is attached to the `websecure`
entrypoint, so every router inherits it. A run against `https://$APP_DOMAIN` collects `429`s
from Traefik as soon as it passes 50 r/s, which is neither the `409`/`422` the test expects
nor a statement about this application. Run the load test against the `web` container over
`edge`, or give the `ticketing` router an empty `...routers.ticketing.middlewares=` override
for the duration of the run. A `429` from the edge is not evidence about the concurrency
contract.

### Restricting the health probes

`/api/health` and `/api/readiness` are unauthenticated by design so a monitor can call them.
To narrow them to your monitor, add a second router to the `web` service — a more specific
rule wins in Traefik, so it takes the two probe paths off the main router:

```yaml
            traefik.http.routers.ticketing-probes.rule: Host(`${APP_DOMAIN}`) && PathPrefix(`/api/health`, `/api/readiness`)
            traefik.http.routers.ticketing-probes.entrypoints: websecure
            traefik.http.routers.ticketing-probes.tls.certresolver: letsencrypt
            traefik.http.routers.ticketing-probes.middlewares: probe-allow
            traefik.http.middlewares.probe-allow.ipallowlist.sourcerange: 1.2.3.4/32
```

`ipallowlist` matches on the peer address, which is the real client here because Traefik is
the edge. Behind a CDN it would need `ipStrategy.depth` instead.

### Resource limits

Every service caps its memory in `compose.prod.yaml`, so one runaway container cannot take
the other applications on the box down with it. Ceilings total ~2.5 GB; steady state is
closer to 900 MB (`docker stats` shows the real figure).

| Service | Limit | Sized by |
|---|---|---|
| `app` | 640M | opcache's 128M plus php-fpm's 5 default children at `memory_limit=256M` |
| `web` | 128M | nginx plus the 10m `limit_req` zone |
| `worker` | 384M | one PHP process that may reach `memory_limit` inside a job |
| `scheduler` | 384M | `schedule:work` plus the `schedule:run` child it forks each minute |
| `mysql` | 768M | a 192M buffer pool plus MySQL 8's baseline, mostly `performance_schema` |
| `redis` | 256M | 2x `maxmemory`, for the copy-on-write spike during an AOF rewrite |

Two of these are load-bearing rather than cosmetic:

**Every PHP limit sits above PHP's own `memory_limit`.** If the cgroup fills first, the
kernel kills the process group — which can mean a php-fpm child inside
`TransactionManager::run()` holding a `SELECT ... FOR UPDATE`, or a worker halfway through a
charge with no compensation recorded. When PHP's own limit binds first it throws instead, the
transaction rolls back, and `--tries=3` retries the job. Lowering these below 256M inverts
that and turns a slow request into lost data.

**Redis is `--maxmemory-policy noeviction`, never `allkeys-lru`.** It is not only a cache
here: it holds the queue, the idempotency keys and the stock counters. Evicting a queued job
silently loses a payment; evicting an idempotency key re-opens the duplicate-purchase window.
Refusing the write instead surfaces as an exception the purchase flow already compensates
for. At 128mb the ceiling is far above the real working set, so reaching it means a leak
worth investigating rather than normal growth.

MySQL's `innodb_buffer_pool_size` is pinned for the same class of reason: InnoDB sizes the
pool against the host's RAM, not the cgroup's, so a memory limit without it would eventually
be enforced by the OOM killer mid-transaction.

## 5. Logs

| Stream | Contents |
|---|---|
| `storage/logs/app-YYYY-MM-DD.log` | Application events, JSON lines, level `info` and above |
| `storage/logs/access-YYYY-MM-DD.log` | One JSON line per request: method, path, status, duration, IP, user id, correlation id |
| `docker compose -f compose.prod.yaml logs web` | Nginx access/error, including requests that never reached PHP |
| `docker compose logs -f traefik` (in `/srv/traefik`) | Edge access log, `429`s from `flood-guard`, and ACME certificate events |

Both application files live in the `ticketing-storage` volume and rotate daily, keeping
`LOG_DAILY_DAYS` / `LOG_ACCESS_DAYS` days (14 by default) — no logrotate rule needed.

```bash
# follow the application log
docker compose -f compose.prod.yaml exec app sh -c 'tail -f storage/logs/app-$(date +%F).log'

# trace one request across both files
docker compose -f compose.prod.yaml exec app sh -c 'grep CORRELATION_ID storage/logs/*.log'
```

Every response carries an `X-Correlation-ID` header, and it is the join key between the two
files. A client reporting an error only has to quote it.

## 6. Deploying an update

```bash
cd /srv/ticketing
git pull

docker compose -f compose.prod.yaml build
docker compose -f compose.prod.yaml exec app php artisan down
docker compose -f compose.prod.yaml up -d            # recreates app, web, worker, scheduler
docker compose -f compose.prod.yaml exec app php artisan migrate --force
docker compose -f compose.prod.yaml exec app php artisan l5-swagger:generate
docker compose -f compose.prod.yaml exec app php artisan up
```

The image is the unit of deployment: `opcache.validate_timestamps=0` means edits inside a
running container have no effect, and the worker holds its code in memory until recreated.

## 7. Verifying the deploy

```bash
curl -s https://api.your-domain.com/                       # JSON index, not a 500
curl -s https://api.your-domain.com/api/health
curl -so /dev/null -w '%{http_code}\n' https://api.your-domain.com/api/readiness
curl -so /dev/null -w '%{http_code}\n' https://api.your-domain.com/api/documentation
curl -sI https://api.your-domain.com/api/health | grep -i 'strict-transport\|x-powered-by'
```

Expected: readiness `200` (or `503` if a dependency is down), documentation `401` without
credentials, HSTS present, and **no** `X-Powered-By`.

Then confirm the client IP survives the proxy — this is the check that proves the rate
limiting is real:

```bash
docker compose -f compose.prod.yaml exec app sh -c 'tail -1 storage/logs/access-$(date +%F).log'
```

The `ip` field must be your workstation's public address, not `172.x.x.x`. If it shows a
Docker-internal address, `TRUSTED_PROXIES` is wrong and every rate limit is sharing one bucket.

Point UptimeRobot (or equivalent) at `/api/readiness`: it returns **503** when MySQL or Redis
is down, which is what makes the alert fire.
