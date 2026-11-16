# Deploying eneba-be to Render

Render has no native PHP runtime, so the API runs as a **Docker** web service. The image (see `Dockerfile`) runs PHP-FPM and Nginx in one container:

- **On build:** installs production Composer dependencies (`--no-dev --optimize-autoloader`), with the `pdo_pgsql` and `intl` extensions.
- **On every start:** `docker/start.sh` caches config, routes and events, runs `migrate --force`, and starts Nginx on the port Render provides (`$PORT`).

## 1. Create the database

1. In Render, go to **New → Postgres**.
2. Pick the same **region** you will use for the web service.
3. Once it is ready, copy its **Internal Database URL**. You will paste it into the web service as `DATABASE_URL`.

## 2. Create the web service

1. Go to **New → Web Service** and connect this repository.
2. **Language / Runtime:** Docker. Render detects the `Dockerfile` in the repo root; leave the Docker command empty, because the image's `CMD` runs `docker/start.sh`.
3. **Region:** same as the database, so the internal URL works.
4. **Health Check Path:** `/up`
5. Add the environment variables below, then deploy.

The first deploy builds the image and runs all migrations against the empty database.

## 3. Environment variables

### Required

| Variable | Value |
|---|---|
| `APP_KEY` | Generate locally with `php artisan key:generate --show` and paste the whole `base64:…` value. |
| `APP_URL` | The service's public URL, e.g. `https://eneba-be.onrender.com`. |
| `DB_CONNECTION` | `pgsql` |
| `DATABASE_URL` | The database's **Internal Database URL**. |
| `FRONTEND_URL` | The storefront origin(s) allowed by CORS, e.g. `https://eneba-fe.onrender.com`. Use commas for several, and no trailing path. |

### Already set by the image (override only if needed)

| Variable | Default |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `LOG_CHANNEL` | `stderr`, so logs appear in Render's **Logs** tab. |
| `LOG_LEVEL` | `info` |
| `PORT` | `10000`. Render sets this itself. |

### Optional

| Variable | Use |
|---|---|
| `APP_NAME` | Shown in the API docs title. |
| `MOCK_PAYMENTS_ENABLED` | Off in production. Set to `true` only on a staging service, to allow `POST /api/v1/orders/{order}/pay`. |
| `DB_SSLMODE` | Defaults to `prefer`. Set `require` if you connect over the external URL. |

## 4. Seed reference data (one time)

Migrations run on every deploy, but seeding does not. After the first successful deploy, seed the platforms, product types, regions, countries, genres, operating systems, side menu and home galleries. In production, `DatabaseSeeder` seeds only that reference data: no products, orders or users.

**Option A: Render Shell** (paid instances). Open the service's **Shell** tab and run:

```bash
php artisan db:seed --force
```

**Option B: from your machine** (works on the free tier). Copy the database's **External Database URL** and run this from the project directory:

```bash
APP_ENV=production DB_CONNECTION=pgsql DATABASE_URL="<external database url>?sslmode=require" php artisan db:seed --force
```

The seeders update rows by slug or label, so running them again is safe: it refreshes names and order without creating duplicates.

## What changes in production

- **Mock payments are off.** `POST /api/v1/orders/{order}/pay` returns `404` unless `MOCK_PAYMENTS_ENABLED=true`. Placing and viewing orders still work. There is no fake key generation in the codebase, so there is nothing else to switch off.
- **API docs are closed.** `/docs/api` and `/docs/api.json` return `403` in production. They stay open locally and on any non-production environment, such as staging with `APP_ENV=staging`.
- **URLs use HTTPS.** Generated URLs, including pagination links, are `https://`. The app trusts Render's proxy headers, so rate limits apply to each client's real IP rather than the load balancer's.
- **CORS** only allows the origins in `FRONTEND_URL`. If the frontend's URL changes, update the variable and redeploy.

## Notes

- **Config changes need a redeploy.** Config is cached when the container starts, so after changing an environment variable, trigger a redeploy (Render does this automatically when you save variables).
- **Running more than one instance:** every instance runs `migrate --force` on start. That is fine for a single instance. With several, move migrations to Render's **Pre-Deploy Command** (`php artisan migrate --force`) and remove the line from `docker/start.sh`.
- **Free instances** sleep after inactivity, so the first request after a pause is slow while the container starts and runs its start-up steps.
- **Testing the image locally:**

  ```bash
  docker build -t eneba-be .
  ```

  ```bash
  docker run --rm -p 8080:10000 -e APP_KEY="$(php artisan key:generate --show)" -e DB_CONNECTION=pgsql -e DATABASE_URL="postgresql://<user>@host.docker.internal:5432/<database>" -e FRONTEND_URL=http://localhost:3000 eneba-be
  ```

  Then open http://localhost:8080/up.
