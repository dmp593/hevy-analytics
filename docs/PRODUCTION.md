# Running this in production

What actually runs, why it is arranged this way, and what to change when the
free tier stops being enough.

---

## The processes

Three things must run. They are separate concerns and, given the budget,
separate services:

| Process | Command | What breaks without it |
|---|---|---|
| Web | `frankenphp php-server --root public` | Everything |
| Queue worker | `php artisan queue:work` | Syncs sit at "queued" forever |
| Scheduler | `php artisan schedule:work` | No hourly syncs, no trial emails |

### What the queue actually does here

Only one kind of job, but it is the one that matters: **`SyncHevyJob`**. A full
Hevy import is dozens of paginated HTTPS round-trips (Hevy caps workout pages
at ten items), so it cannot run inside a web request — the browser would hang
and a request timeout would leave the import half-applied.

Three properties the job relies on, all of which the worker configuration has
to preserve:

- **`ShouldBeUnique`**, keyed by user. Deduplication happens at *dispatch*, so
  five impatient clicks produce one job. `uniqueFor = 900` releases the lock if
  a worker dies holding it, so a crash cannot wedge an account out of syncing
  forever.
- **`timeout = 600`**. A long history legitimately takes minutes.
- **Failure must be visible.** `SyncHevyJob::failed()` marks the `sync_logs`
  row failed, so the UI shows "sync failed" instead of "queued" forever. This
  only runs if the worker exits cleanly; a `SIGKILL` skips it.

That last point is why shutdown handling matters more than it looks.

### The single-container arrangement (what is running now)

`docker/start.sh` runs all three in one container, because a free tier gives
one container and bills background workers separately. It is a compromise, and
these are its actual properties — not an aspiration:

- **Supervision with backoff.** Each worker runs in a restart loop. A process
  that dies in under a minute doubles the restart delay up to 60s, so a
  crash-loop caused by a bad config does not respawn twice a second and bury
  its own error in log spam.
- **Recycling.** `--max-time=3600 --max-jobs=500`. A long-lived PHP process
  accumulates memory; recycling is the cheap defence against a slow leak
  becoming an OOM at four in the morning.
- **Graceful drain.** The script does *not* `exec` the web server. If it did,
  SIGTERM would reach the server only and the container teardown would kill a
  running sync mid-flight. Instead it traps SIGTERM, stops the restart loops
  first so they cannot respawn what is being drained, signals the worker —
  which finishes the job in hand and exits — and only then stops the server.
- **`--tries=3 --backoff=10`.** Hevy's API returning a 500 is a transient
  thing; retrying three times ten seconds apart beats failing the import.

**What this arrangement does not give you**, and you should know before it
bites:

- A queue job and an HTTP request compete for the same CPU. A full import for
  one athlete makes the site slower for everyone during those minutes.
- On a sleeping free tier, **the scheduler sleeps too**. Hourly syncs and trial
  emails only fire while the container is awake, and catch up on the next
  visit. This is fine for testing and wrong for paying users.
- One container means one failure domain: an OOM takes down the site *and* the
  worker.

### The split arrangement (what to move to)

The moment there are paying users, split it. On Render that is three services
off the same repository and the same Dockerfile:

| Service | Type | Command | Plan |
|---|---|---|---|
| `hevy-analytics` | Web | default entrypoint, `WORKER_ENABLED=false` | Starter |
| `hevy-analytics-worker` | Background worker | `php artisan queue:work --tries=3 --backoff=10 --max-time=3600` | Starter |
| `hevy-analytics-scheduler` | Cron job, `* * * * *` | `php artisan schedule:run` | free |

`WORKER_ENABLED=false` is read by `start.sh` and turns off both loops, so the
web container stops competing with itself. Everything else is unchanged: the
same image, the same environment variables, the same database.

Note the scheduler difference — a cron service runs `schedule:run` once a
minute, where the single container runs `schedule:work` as a daemon. Both are
correct; the daemon exists because a free tier has no cron.

### Swapping the queue driver

`QUEUE_CONNECTION=database` today, which is right at this size: no extra
service, and the jobs table is visible in the same database as everything else.
Move to Redis when the worker count grows past one or two — the database driver
polls, and several workers polling the same table start contending on locks.
That is a one-line change plus a Redis URL; nothing in the app knows the
difference.

---

## Swapping any provider

Nothing about the app is tied to a vendor. Each is one environment variable
away from being replaced, which is deliberate — free tiers change their terms.

| Concern | Now | Env | Swap to |
|---|---|---|---|
| Hosting | Render | — | Fly.io, Railway, a VPS. The Dockerfile is standard. |
| Database | Neon | `DATABASE_URL` | Any Postgres. Use `MIGRATE_FROM_DATABASE_URL` to carry the data over (below). |
| Email | Resend | `MAIL_MAILER`, `RESEND_API_KEY` | Postmark, SES, or plain SMTP — Laravel ships all of them. |
| Photos | Cloudflare R2 | `PHOTO_DISK`, `R2_*` | Any S3-compatible store: Backblaze B2, Wasabi, S3 itself. |
| Payments | Paddle | `PADDLE_*` | Harder — Cashier Paddle is a dependency. But `App\Billing\State` is the only class that asks Cashier anything, so a swap rewrites one file rather than auditing every call site. |
| AI | any OpenAI/Anthropic-compatible | `AI_*`, per-user keys | Add a driver in `app/Services/AI/Drivers`. |

### Moving the database to another host

Built into the boot script, because doing it by hand at 2am is how data gets
lost:

1. Set `DATABASE_URL` to the **new** database.
2. Set `MIGRATE_FROM_DATABASE_URL` to the **old** one. Use the provider's
   *internal* connection string if both live at the same host — external
   endpoints are frequently unreachable from inside their own network.
3. Redeploy. The script dumps the source, **verifies the dump contains tables**,
   and only then restores. It refuses to touch a target that already has data
   unless `MIGRATE_REPLACE=true`.
4. Remove both variables.

If the dump fails, the boot continues on an untouched target and the site comes
up on a fresh schema. That is deliberate: an optional import must never be able
to take production down.

---

## Environment variables

Everything the app reads, and whether production needs it.

### Required

| Variable | Notes |
|---|---|
| `APP_KEY` | `php artisan key:generate --show`. Rotating it makes every encrypted API key unreadable. |
| `APP_URL` | Used in emails and the Paddle webhook URL. |
| `DATABASE_URL` | Postgres connection string. |
| `APP_ENV=production`, `APP_DEBUG=false` | `APP_DEBUG=true` in production leaks configuration in stack traces. |

### Infrastructure

| Variable | Value here | Why |
|---|---|---|
| `TRUSTED_PROXIES` | `*` | Behind a load balancer, every request appears to come from the proxy, so per-IP rate limits would be shared by the whole internet. `*` is safe only when the container is unreachable except through that proxy. |
| `SESSION_DRIVER`, `QUEUE_CONNECTION`, `CACHE_STORE` | `database` | One less service to run. Swap `QUEUE_CONNECTION` to `redis` when workers multiply. |
| `LOG_CHANNEL` | `stderr` | Container logs, not a file on an ephemeral disk. |
| `WORKER_ENABLED` | unset (true) | `false` on the web service once the queue runs separately. |

### Features

| Variable | Notes |
|---|---|
| `PHOTO_DISK` | `local` in development, `r2` in production. |
| `R2_*` | See `docs/SERVICES.md`. |
| `MAIL_MAILER`, `RESEND_API_KEY`, `MAIL_FROM_*` | Until set, mail goes to the log. |
| `AUTO_VERIFY_EMAIL` | `true` only while there is no mail provider — otherwise verification links go nowhere and every new account is locked out. **Turn it off the moment mail works.** |
| `PADDLE_*`, `BILLING_*` | Billing stays off until these are set; the app runs on trial and free tiers. |
| `HEVY_AUTO_SYNC_HOURS` | Staleness window for the login sync. `0` disables it. |
| `AI_*` | The included AI allowance. Unset means athletes must bring their own key. |
| `BOOTSTRAP_*` | Operator accounts, created once at boot. |

---

## Health, logs and recovery

- **Health check**: `/up`. Render polls it; a failing check blocks the deploy,
  which is what caught the capability and database problems during setup.
- **Logs**: `LOG_CHANNEL=stderr`, so everything is in the platform's log stream.
- **A stuck sync**: `sync_logs` rows sitting at `queued` for more than two
  minutes mean nothing is consuming the queue. The dashboard says so to the
  user rather than spinning silently. Check the worker is alive.
- **A failed deploy**: the release steps run before the server binds, so a
  broken migration fails the deploy instead of serving a half-migrated app.
