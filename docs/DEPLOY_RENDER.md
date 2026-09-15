# Deploy Betedesta API on Render

The backend is a Laravel 12 API. Render does not ship a native PHP buildpack for every case, so this repo uses **Docker** (Render’s recommended Laravel path).

## What was added in the repo

| File | Purpose |
| --- | --- |
| `Dockerfile` | **PHP 8.4** + nginx + FPM (build from monorepo root) |
| `docker/nginx.conf` / `docker/start.sh` | nginx + container entrypoint |
| `backend/Dockerfile` | Alternate build when Root Directory is `backend` |
| `backend/scripts/00-laravel-deploy.sh` | Composer, caches, migrate (optional seed) |
| `backend/config/cors.php` | Allows `FRONTEND_URL` / `CORS_ALLOWED_ORIGINS` |
| `render.yaml` | Optional Blueprint |

## Before you start

1. Push this monorepo to GitHub / GitLab / Bitbucket.
2. Create a [Render](https://dashboard.render.com) account and connect that repo.
3. On your machine, generate an app key (do not commit it):

```bash
cd backend
php artisan key:generate --show
```

Copy the full `base64:...` value. You will paste it into Render as `APP_KEY`.

---

## Option A — Manual setup (recommended first time)

### Critical Render settings (monorepo, PHP 8.4)

| Field | Value |
| --- | --- |
| **Root Directory** | *(leave empty)* |
| **Runtime** | Docker |
| **Dockerfile Path** | `./Dockerfile` |
| **Docker Context** | `.` |
| **Health check** | `/up` |
| **PORT** (env) | `80` |

The image is **PHP 8.4** (required by `composer.lock` / Symfony 8). Do not use `richarvey/nginx-php-fpm:3.1.6` — that is PHP 8.2 and Composer will fail.

If Root Directory is set to `backend` while the context is still the repo root, `vendor/` will be missing and you will see `Failed opening required '.../vendor/autoload.php'`.

### 1. Create a PostgreSQL database

1. Render Dashboard → **New** → **PostgreSQL**.
2. Name it e.g. `betedesta-db`.
3. Region: pick one and **reuse it** for the web service.
4. Plan: **Free** is fine for MVP.
5. Create. Open the DB and copy **Internal Database URL** (looks like `postgresql://...`).

### 2. Create the Web Service

1. **New** → **Web Service** → select this repo.
2. Settings:

| Field | Value |
| --- | --- |
| Name | `omniva-restaurant` (or any name) |
| Region | Same as the database |
| Root Directory | *(empty — use repo-root Dockerfile)* |
| Runtime | **Docker** |
| Dockerfile Path | `./Dockerfile` |
| Docker Context | `.` |
| Instance type | Free / Starter |

3. Health check path: `/up`

### 3. Environment variables

In the Web Service → **Environment**, add:

| Key | Value |
| --- | --- |
| `APP_NAME` | `Betedesta` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | *(paste from `php artisan key:generate --show`)* |
| `APP_URL` | `https://YOUR-SERVICE.onrender.com` *(set after first deploy if needed)* |
| `FRONTEND_URL` | `https://omniva.evella.et` |
| `CORS_ALLOWED_ORIGINS` | `https://omniva.evella.et` |
| `LOG_CHANNEL` | `stderr` |
| `DB_CONNECTION` | `pgsql` |
| `DB_URL` | *(Internal Database URL from step 1)* |
| `SESSION_DRIVER` | `database` |
| `CACHE_STORE` | `database` |
| `QUEUE_CONNECTION` | `sync` |
| `PORT` | `80` |
| `RUN_SEEDERS` | `true` **only on the first deploy**, then change to `false` |

Notes:

- Use the **Internal** DB URL when the web service and database are in the same Render region.
- Do **not** use SQLite on Render — the filesystem is ephemeral.
- Sanctum here uses **Bearer tokens**, so you do not need cookie/session CORS credentials.

### 4. Deploy

Click **Create Web Service**. Render builds the Docker image, runs `scripts/00-laravel-deploy.sh` on start (composer, config/route/view cache, migrate, optional seed), then serves `public/` via nginx.

When it is live:

1. Open `https://YOUR-SERVICE.onrender.com/up` — you should see OK / Laravel health JSON.
2. Open `https://YOUR-SERVICE.onrender.com/api/v1/login` with POST is expected to fail without a body (405/422), which still proves the API is up.
3. Set `APP_URL` to that HTTPS URL if you left a placeholder.
4. Set `RUN_SEEDERS=false` and **Manual Deploy** once so later deploys do not re-seed.

### 5. Point the frontend at the API

In Nuxt (local or hosted):

```env
NUXT_PUBLIC_API_BASE=https://YOUR-SERVICE.onrender.com/api/v1
```

And keep `FRONTEND_URL` / `CORS_ALLOWED_ORIGINS` on the API matching the browser origin of the Nuxt app.

### 6. First login (after seeding)

| Role | Email | Password |
| --- | --- | --- |
| Admin | `admin@omniva.test` | `Password123!` |

Change these in production when you go live for real.

---

## Option B — Blueprint (`render.yaml`)

1. Render Dashboard → **New** → **Blueprint**.
2. Select this repo (Blueprint file at repo root: `render.yaml`).
3. Fill in sync:false secrets when prompted: `APP_KEY`, `APP_URL`, `FRONTEND_URL`, `CORS_ALLOWED_ORIGINS`.
4. Deploy. The Blueprint creates `betedesta-db` and wires `DB_URL` automatically.

Set `RUN_SEEDERS=true` once in the service env if you want the demo menu/users, then turn it off.

---

## After every code push

With auto-deploy on:

1. Render rebuilds the Docker image from `backend/`.
2. On container start, migrations run with `php artisan migrate --force`.
3. No need to run Composer on your laptop for production.

---

## Common issues

| Symptom | Fix |
| --- | --- |
| `open Dockerfile: no such file or directory` | Use repo-root `./Dockerfile` with empty Root Directory, or set Root Directory to `backend`. |
| Composer: PHP >= 8.4.1 required | You are on an old PHP 8.2 image. Use the repo-root Dockerfile (`php:8.4-fpm-alpine`). |
| No open ports detected | Set env `PORT=80`. |
| Build fails / Composer errors | Confirm the image copies `backend/` (root Dockerfile) or Root Directory is `backend`. |
| 502 / app never healthy | Check logs for missing `APP_KEY` or bad `DB_URL`. Health path must be `/up`. |
| DB connection refused | Use **Internal** URL; web service and DB must share region. |
| CORS errors in browser | Set `FRONTEND_URL` / `CORS_ALLOWED_ORIGINS` to `https://omniva.evella.et`, then **Manual Deploy**. If `/up` is 500, fix vendor/DB first — CORS is a side effect. |
| `/up` or `/api/v1/*` returns 404/500 | Redeploy with root Dockerfile + `PORT=80`. Check logs for `vendor/autoload.php`. |
| Empty login / no users | Set `RUN_SEEDERS=true`, redeploy once, then set `false`. |
| Free tier spin-down | First request after idle can take ~30–60s; that is normal on Free. |

---

## Optional: run a one-off seed without redeploying

Render Shell (paid plans) or a one-off job:

```bash
php artisan db:seed --force
```

On Free, flip `RUN_SEEDERS=true`, deploy, then flip back.

---

## What not to do

- Do not commit `.env` or a real `APP_KEY`.
- Do not use `DB_CONNECTION=sqlite` on Render.
- Do not set `APP_DEBUG=true` in production.
- Do not leave `RUN_SEEDERS=true` forever (it will reset demo data expectations and can fail on unique emails).
