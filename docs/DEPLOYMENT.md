# Deploying to the VPS

The stack is Dockerised and shares the host with your other applications (`eventhub`,
`applications`), so it must not claim ports 80/443 for itself. It runs behind a **shared
reverse proxy** on a shared Docker network and publishes nothing of its own.

`compose.yaml` in this repo is the Laravel Sail **development** runtime and is not used here.
Production is `compose.prod.yaml` plus `docker/production/`.

---

## Layout

```
                     :80/:443
                        |
          +-------------v--------------+
          |  shared reverse proxy      |   (Traefik / nginx-proxy / Caddy)
          |  network: proxy            |
          +--+---------+------------+--+
             |         |            |
      ticketing_web  eventhub_web  applications_web      <- no published ports
             |
   +---------v---------+
   |  network: internal (private to this stack)
   |  app (php-fpm) - worker - scheduler - mysql - redis
   +-------------------+
```

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

Create the shared network if the other apps have not already:

```bash
docker network create proxy || true
docker network ls | grep proxy
```

If `eventhub` and `applications` already sit behind a proxy, reuse **that** network name and
change the `proxy` entry under `networks:` in `compose.prod.yaml` to match.

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
| `TRUSTED_PROXIES=*` | **Without it every per-IP rate limit collapses into one bucket.** `*` is safe *only* because no port is published — see below. |
| `DB_PASSWORD`, `DB_ROOT_PASSWORD`, `REDIS_PASSWORD` | The services are private to the stack, but a compromised sibling container on a shared network should still hit a password. |
| `LOG_CHANNEL=daily_json` | JSON lines with daily rotation, instead of one unbounded text file. |
| `DOCS_AUTH_PASSWORD` | Basic-auth password for `/api/documentation`. Unset means the route answers **503** — never open. |
| `SENTRY_LARAVEL_DSN` | Error monitoring. Leave empty to disable Sentry entirely. |
| `SESSION_SECURE_COOKIE=true` | The proxy terminates TLS, so this is correct. |
| `CORS_ALLOWED_ORIGINS` | `*` is only defensible while authentication stays bearer-token only. |

**Why `TRUSTED_PROXIES=*` is safe here.** `X-Forwarded-For` is attacker-controlled input.
Trusting any proxy is only sound when the client cannot reach the app directly, which is why
`compose.prod.yaml` declares no `ports:` on `web` — the sole route in is the reverse proxy on
the shared network. If you ever publish a port for debugging, change this to the proxy
container's address for as long as that port is open.

## 3. First deploy

```bash
docker compose -f compose.prod.yaml up -d --build

docker compose -f compose.prod.yaml exec app php artisan key:generate --force
docker compose -f compose.prod.yaml exec app php artisan migrate --force
docker compose -f compose.prod.yaml exec app php artisan l5-swagger:generate

# key:generate invalidated the config cached at container start
docker compose -f compose.prod.yaml restart app worker scheduler
```

## 4. Wire it to the reverse proxy

**Traefik** — add labels to the `web` service in `compose.prod.yaml`:

```yaml
        labels:
            - 'traefik.enable=true'
            - 'traefik.docker.network=proxy'
            - 'traefik.http.routers.ticketing.rule=Host(`api.your-domain.com`)'
            - 'traefik.http.routers.ticketing.entrypoints=websecure'
            - 'traefik.http.routers.ticketing.tls.certresolver=letsencrypt'
            - 'traefik.http.services.ticketing.loadbalancer.server.port=80'
```

**nginx-proxy** — add environment instead:

```yaml
        environment:
            VIRTUAL_HOST: api.your-domain.com
            LETSENCRYPT_HOST: api.your-domain.com
            VIRTUAL_PORT: 80
```

**A plain Nginx (container or host)** — a vhost pointing at the container:

```nginx
server {
    listen 443 ssl http2;
    server_name api.your-domain.com;

    ssl_certificate     /etc/letsencrypt/live/api.your-domain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/api.your-domain.com/privkey.pem;

    location / {
        proxy_pass http://ticketing-web-1:80;
        proxy_set_header Host              $host;
        proxy_set_header X-Real-IP         $remote_addr;
        proxy_set_header X-Forwarded-For   $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_set_header X-Forwarded-Host  $host;
    }
}
```

All four `X-Forwarded-*` headers matter: without `Proto` the app builds `http://` URLs and
believes the connection is insecure, and without `For` the rate limiting is meaningless.

### Rate limiting: three layers, on purpose

| Layer | Scope | What it stops |
|---|---|---|
| Application (`throttle:` middleware) | Per user, per email, per IP | Business limits: 5 login attempts/min per email, 60 purchases/min per user, 60/min on public reads |
| Container Nginx (`limit_req`, 30 r/s) | Per IP, before PHP | Volumetric abuse; still answers when PHP-FPM has no free workers |
| Reverse proxy | Per host | Whatever you already apply to the other apps |

The application limits cannot be expressed in Nginx — it knows nothing about users or emails —
and the Nginx limit cannot be expressed in the application, which runs too late to help.

### Restricting the health probes

`/api/health` and `/api/readiness` are unauthenticated by design so a monitor can call them.
To narrow them to your monitor, add to the reverse-proxy vhost:

```nginx
location ~ ^/api/(health|readiness)$ {
    allow 1.2.3.4;      # your uptime monitor
    deny all;
    proxy_pass http://ticketing-web-1:80;
}
```

## 5. Logs

| Stream | Contents |
|---|---|
| `storage/logs/app-YYYY-MM-DD.log` | Application events, JSON lines, level `info` and above |
| `storage/logs/access-YYYY-MM-DD.log` | One JSON line per request: method, path, status, duration, IP, user id, correlation id |
| `docker compose -f compose.prod.yaml logs web` | Nginx access/error, including requests that never reached PHP |

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
