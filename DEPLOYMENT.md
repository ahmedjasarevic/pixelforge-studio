# Deployment

The app needs four things in production:

1. PHP 8.3+ with `bcmath` and a database (PostgreSQL recommended),
2. a **web** process,
3. a **queue worker** (`php artisan queue:work`),
4. a **scheduler** (`php artisan schedule:run` every minute, or `php artisan schedule:work`).

No uploaded files or persistent disk are required: generated images stay on Pixelforge's signed URLs, and all state lives in the database.

## Environment variables

| Variable                         | Required    | Example / notes                                                                           |
| -------------------------------- | ----------- | ----------------------------------------------------------------------------------------- |
| `APP_NAME`                       | yes         | `Pixelforge Studio`                                                                       |
| `APP_ENV`                        | yes         | `production`                                                                              |
| `APP_KEY`                        | yes         | generate with `php artisan key:generate --show`                                           |
| `APP_DEBUG`                      | yes         | `false`                                                                                   |
| `APP_URL`                        | yes         | `https://your-domain.example`                                                             |
| `APP_TIMEZONE`                   | no          | leave `UTC`; the report depends on UTC completion days                                    |
| `DB_CONNECTION`                  | yes         | `pgsql` (or `sqlite` for a single-node demo)                                              |
| `DB_URL`                         | yes*        | full PostgreSQL URL, or use `DB_HOST`/`DB_PORT`/`DB_DATABASE`/`DB_USERNAME`/`DB_PASSWORD` |
| `SESSION_DRIVER`                 | yes         | `database`                                                                                |
| `CACHE_STORE`                    | yes         | `database` or `redis`                                                                     |
| `QUEUE_CONNECTION`               | yes         | `database` or `redis`                                                                     |
| `PIXELFORGE_BASE_URL`            | yes         | `https://pixelforge.promptedit.dev/v1`                                                    |
| `PIXELFORGE_API_KEY`             | yes         | the **deployed environment's own** key (`pf_...`)                                         |
| `PIXELFORGE_WEBHOOK_SECRET`      | yes         | the signing secret shown for that key (`whsec_...`)                                       |
| `PIXELFORGE_MARKUP_BASIS_POINTS` | no          | `2500` = 25%                                                                              |
| `LOG_CHANNEL`                    | no          | `stderr` in Docker                                                                        |
| `RUN_MIGRATIONS`                 | Docker only | `true` on the web role, `false` on worker/scheduler                                       |
| `RUN_SEEDER`                     | Docker only | `true` on the first deploy if you want the demo/admin accounts                            |
| `ROLE`                           | Docker only | `web` (default), `worker`, or `scheduler`                                                 |

\* Use either `DB_URL` or the individual `DB_*` values.

Use **one Pixelforge API key for the deployed environment** and another for local development. Each key has its own webhook secret and its own usage report, which is what makes the admin report reconcile exactly.

## Option A - Laravel Cloud

1. Push this repository to GitHub.
2. Create a new Laravel Cloud application from the repository and add a PostgreSQL database.
3. Set the environment variables above. Use the deployed key and secret.
4. Make sure the asset build runs (`npm ci && npm run build`) as part of deployment. Laravel Cloud detects Laravel/Node projects automatically; if not, add the commands in the environment settings.
5. Deploy, then run the seeder once from the console:
    ```bash
    php artisan db:seed --force
    ```
    (The seeder is idempotent and creates the demo/admin accounts with credits. Skip this if you do not want public demo accounts.)
6. Create a **queue worker**:
    ```bash
    php artisan queue:work --sleep=2 --tries=8 --timeout=60
    ```
7. Enable the **scheduler** (Laravel Cloud has a scheduler toggle; otherwise use `* * * * * php artisan schedule:run`).
8. In Pixelforge, set the key's webhook URL to:
    ```
    https://<your-app-domain>/webhooks/pixelforge
    ```
    Press **Send test event**. The dashboard should record a `204` response.
9. Generate a few images from the demo account so the deployed app has history and usage to inspect:
   ```bash
   php artisan pixelforge:demo
   php artisan pixelforge:poll
   ```
   `pixelforge:demo` queues three successful images (low, medium with a reference, high) plus one deliberate `[fail]` generation, all with `[succeed]`/`[fail]` so the result is deterministic.
10. Update the live URL in `README.md` and submit it.

## Option B - Railway (Docker)

The included `Dockerfile` builds assets and the PHP application. Railway injects `PORT`; the entrypoint rewrites Apache's listener automatically.

1. Create a Railway project from the GitHub repository and add a **PostgreSQL** plugin.
2. Set the service variables using the table above. For the database, set `DB_URL` to Railway's `DATABASE_URL` reference (or the individual `DB_*` variables).
3. Set `ROLE=web`, `RUN_MIGRATIONS=true`, and `RUN_SEEDER=true` on the first deploy.
4. Generate a public domain under **Settings → Networking** and set `APP_URL` to it.
5. Duplicate the service twice:
    - **worker**: `ROLE=worker`, `RUN_MIGRATIONS=false`, `RUN_SEEDER=false`
    - **scheduler**: `ROLE=scheduler`, `RUN_MIGRATIONS=false`, `RUN_SEEDER=false`
6. Set the Pixelforge webhook URL to `https://<railway-domain>/webhooks/pixelforge`, copy the secret into `PIXELFORGE_WEBHOOK_SECRET`, and redeploy/restart.
7. Verify `https://<railway-domain>/up` returns 200, then generate a test image.

## Option C - VPS (Docker)

On any host with Docker:

```bash
docker build -t pixelforge-studio .

# PostgreSQL (or use an external managed database)
docker run -d --name pixelforge-db \
  -e POSTGRES_PASSWORD=change-me \
  -e POSTGRES_DB=pixelforge \
  -v pixelforge-db:/var/lib/postgresql/data \
  postgres:16

# Web
docker run -d --name pixelforge-web --restart unless-stopped \
  --env-file .env.production -e ROLE=web -e PORT=8080 -p 8080:8080 \
  pixelforge-studio

# Queue worker
docker run -d --name pixelforge-worker --restart unless-stopped \
  --env-file .env.production -e ROLE=worker \
  pixelforge-studio

# Scheduler
docker run -d --name pixelforge-scheduler --restart unless-stopped \
  --env-file .env.production -e ROLE=scheduler \
  pixelforge-studio
```

Put nginx/Caddy in front of port 8080 for TLS. With PHP-FPM + Forge/Plint instead, configure `queue:work` and `schedule:run` as described in the Laravel docs.

## Webhook setup

1. Open the Pixelforge dashboard for the **deployed** API key.
2. Set the webhook URL to `https://<app-domain>/webhooks/pixelforge`.
3. Copy the key's signing secret into `PIXELFORGE_WEBHOOK_SECRET` and restart the app/worker.
4. Press **Send test event**. A healthy endpoint returns **204**.
5. Check the delivery log after a real generation: `generation.processing` and `generation.succeeded` (or `generation.failed`) should be delivered.

If webhooks are temporarily down, the scheduler's `pixelforge:poll` command recovers the state within a minute. The app never relies on webhooks alone.

## Post-deploy checklist

- [ ] `APP_DEBUG=false`, `APP_ENV=production`, `APP_KEY` set.
- [ ] `https://<domain>/up` returns 200.
- [ ] Login works and the demo account has credits.
- [ ] Queue worker is processing (`generation` moves from queued to processing/succeeded).
- [ ] Scheduler runs `pixelforge:poll` every minute.
- [ ] Pixelforge webhook test returns 204.
- [ ] Generate at least: one low-quality image, one medium image with a reference URL, and one `[fail]` prompt (it must end failed and free).
- [ ] Open `/admin/report`; the reconciliation banner is green and provider cost equals `GET /v1/usage` for the same range.
- [ ] Add the live URL to `README.md`.
