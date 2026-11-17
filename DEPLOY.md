# Deploying eneba-be to Render

Render has no native PHP runtime, so the API runs as a **Docker** web service. The image (see `Dockerfile`) runs PHP-FPM and Nginx in one container:

- **On build:** installs production Composer dependencies (`--no-dev --optimize-autoloader`), with the `pdo_pgsql` and `intl` extensions.
- **On every start:** `docker/start.sh` caches config, routes and events, runs `migrate --force`, and starts Nginx on the port Render provides (`$PORT`).

## 0. Pick your domains first

Users sign in with **emailed magic links** (there are no passwords), and stay signed in with **Sanctum session cookies**. Browsers only send those cookies when the storefront and the API are on the **same site**, so they need to share a parent domain:

| Works | Does not work |
|---|---|
| `shop.example.com` and `api.example.com` | `eneba-fe.onrender.com` and `eneba-be.onrender.com` |

`onrender.com` is a public suffix, so two `*.onrender.com` services count as different sites. Login would appear to succeed, but no later request would be authenticated.

Add a **custom domain** to both Render services (each service's **Settings → Custom Domains**), e.g. `shop.example.com` for the storefront and `api.example.com` for the API. Use those domains in all the variables below.

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
| `APP_URL` | The API's public URL, e.g. `https://api.example.com`. |
| `DB_CONNECTION` | `pgsql` |
| `DATABASE_URL` | The database's **Internal Database URL**. |
| `FRONTEND_URL` | The storefront origin, e.g. `https://shop.example.com`. Use commas for several, and no trailing path. It is used for CORS and as the target of sign-in links (`{FRONTEND_URL}/auth/magic?token=…`). The first origin is used for links. |
| `SANCTUM_STATEFUL_DOMAINS` | The storefront's host, without the scheme, e.g. `shop.example.com`. Requests from these hosts are authenticated with the session cookie and must send the CSRF header. |
| `SESSION_DOMAIN` | The shared parent domain with a leading dot, e.g. `.example.com`, so the storefront can read the `XSRF-TOKEN` cookie. |

### Email via Brevo (required to sign in)

Sign-in links are sent by email, so without a working mailer nobody can sign in. Production sends through **Brevo's HTTP API** (port 443, so Render's SMTP restrictions don't matter). With the default `log` mailer, emails are only written to `storage/logs/mail.log` inside the container and never delivered.

| Variable | Value |
|---|---|
| `MAIL_MAILER` | `brevo` |
| `BREVO_API_KEY` | A Brevo API key (starts with `xkeysib-`). See the Brevo setup below. |
| `MAIL_FROM_ADDRESS` | The sender, e.g. `no-reply@example.com`. It must be on a domain verified in Brevo. |
| `MAIL_FROM_NAME` | The sender name shown to users, e.g. `Eneba`. |

#### Brevo setup

1. **Verify your domain:** in Brevo, go to **Senders, Domains & Dedicated IPs → Domains → Add a domain**, enter the domain of `MAIL_FROM_ADDRESS` (e.g. `example.com`), and add the DNS records Brevo shows: the Brevo code, DKIM, and DMARC. Wait until Brevo marks the domain **Authenticated**. Mail from an unauthenticated domain is often rejected or lands in spam.
2. **Create an API key:** go to **SMTP & API → API keys → Generate a new API key**. Copy it into `BREVO_API_KEY` on Render; Brevo shows it only once.
3. **Authorized IPs:** if **Security → Authorized IPs** is turned on for your account, Brevo blocks API calls from unknown addresses. Either add your Render service's outbound IPs (Render dashboard → the service → **Connect → Outbound**) or turn the restriction off. Otherwise every send fails with `401`.
4. **Sending limits:** Brevo's free plan sends up to 300 emails a day. Each sign-in link is one email.

If Brevo rejects an email or can't be reached, `POST /api/auth/magic-link` returns `503` ("We could not send the sign-in email. Please try again."), the new link is discarded, and the user's earlier link keeps working. The error, including Brevo's reason, appears in Render's **Logs** tab.

### Already set by the image or app (override only if needed)

| Variable | Default |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `LOG_CHANNEL` | `stderr`, so logs appear in Render's **Logs** tab. |
| `LOG_LEVEL` | `info` |
| `PORT` | `10000`. Render sets this itself. |
| `SESSION_SECURE_COOKIE` | `true` in production, so cookies are only sent over HTTPS. |
| `SESSION_DRIVER` | `database` |

### Optional

| Variable | Use |
|---|---|
| `APP_NAME` | Shown in the API docs title. |
| `MOCK_PAYMENTS_ENABLED` | Off in production. Set to `true` only on a staging service, to allow `POST /api/v1/orders/{order}/pay`. |
| `DB_SSLMODE` | Defaults to `prefer`. Set `require` if you connect over the external URL. |
| `SESSION_LIFETIME` | Minutes of inactivity before a session expires. Defaults to `120`. |

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

## 5. Check that login works

After deploying both services, sign in on the storefront with your email. Then confirm in the browser's developer tools:

1. `GET https://api.example.com/sanctum/csrf-cookie` returns `204` and sets the `XSRF-TOKEN` and `laravel-session` cookies for `.example.com`, marked `Secure`.
2. `POST https://api.example.com/api/auth/magic-link` returns `202`, and the email arrives with a link to `https://shop.example.com/auth/magic?token=…`.
3. Opening the link makes the storefront call `POST https://api.example.com/api/auth/magic-link/verify`, which returns `200` with the user.
4. `GET https://api.example.com/api/v1/me` returns the user, not `401`.

If no email arrives, check `MAIL_MAILER`, `BREVO_API_KEY` and `MAIL_FROM_ADDRESS`, Render's logs, and Brevo's **Transactional → Logs**. A `503` from `POST /api/auth/magic-link` means Brevo refused or couldn't be reached; the log entry gives the reason. If step 4 returns `401`, check `SANCTUM_STATEFUL_DOMAINS` and `SESSION_DOMAIN`. If you get `419`, the storefront is not sending the `X-XSRF-TOKEN` header. If the browser reports a CORS error, check `FRONTEND_URL`.

## What changes in production

- **Mock payments are off.** `POST /api/v1/orders/{order}/pay` returns `404` unless `MOCK_PAYMENTS_ENABLED=true`. Placing and viewing orders still work. There is no fake key generation in the codebase, so there is nothing else to switch off.
- **API docs are closed.** `/docs/api` and `/docs/api.json` return `403` in production. They stay open locally and on any non-production environment, such as staging with `APP_ENV=staging`.
- **URLs and cookies use HTTPS.** Generated URLs, including pagination links, are `https://`, and session cookies are marked `Secure`. The app trusts Render's proxy headers, so rate limits apply to each client's real IP rather than the load balancer's.
- **CORS** only allows the origins in `FRONTEND_URL`, with cookies allowed. If the frontend's URL changes, update the variable and redeploy.

## Authentication endpoints

Sign-in is passwordless. These are documented in the API spec (on non-production environments).

| Endpoint | What it does | Rate limit |
|---|---|---|
| `POST /api/auth/magic-link` | Emails a sign-in link. Optional `redirect`: a storefront path such as `/cart` | 5 per minute per IP, and 3 per minute per email |
| `POST /api/auth/magic-link/verify` | Exchanges the link's token for a session; creates the account on first sign-in; returns the user and the `redirect`. Optional `remember: true` | 10 per minute per IP |
| `POST /api/auth/logout` | Ends the session | none |
| `GET /api/v1/me` | Returns the signed-in user | none |

- **Links expire after 15 minutes** and work once. Requesting a new link cancels earlier unused ones.
- **The token is never stored:** `magic_login_tokens` keeps only its SHA-256 hash, plus the email address and redirect.
- **Accounts are created on first sign-in,** not when a link is requested, so nobody gets an account for an address they don't control. `POST /api/auth/magic-link` answers `202` for every address, so it doesn't reveal which are registered.
- **Following a link verifies the email address.**
- **Redirects stay on the storefront:** `redirect` must be a path starting with a single `/`. The storefront appends it to its own origin.
- **Remember me:** without `remember`, users stay signed in until the session expires (`SESSION_LIFETIME`, 120 minutes of inactivity by default). With `remember: true`, a long-lived cookie keeps them signed in until they log out.

## Notes

- **Config changes need a redeploy.** Config is cached when the container starts, so after changing an environment variable, trigger a redeploy (Render does this automatically when you save variables).
- **Running more than one instance:** every instance runs `migrate --force` on start. That is fine for a single instance. With several, move migrations to Render's **Pre-Deploy Command** (`php artisan migrate --force`) and remove the line from `docker/start.sh`.
- **Free instances** sleep after inactivity, so the first request after a pause is slow while the container starts and runs its start-up steps.
- **Testing the image locally:**

  ```bash
  docker build -t eneba-be .
  ```

  ```bash
  docker run --rm -p 8080:10000 -e APP_KEY="$(php artisan key:generate --show)" -e DB_CONNECTION=pgsql -e DATABASE_URL="postgresql://<user>@host.docker.internal:5432/<database>" -e FRONTEND_URL=http://localhost:5173 -e SANCTUM_STATEFUL_DOMAINS=localhost:5173 -e SESSION_SECURE_COOKIE=false eneba-be
  ```

  Then open http://localhost:8080/up. `SESSION_SECURE_COOKIE=false` is only for testing over plain HTTP locally.
