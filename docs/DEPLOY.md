# Deploying for free

Two paths. Both end with a public URL, both cost €0, and both need something
only you can do: create the hosting account. Everything the repository can
prepare is already prepared.

> **Already deployed?** `deploy/render.sh` does the whole of Path A through
> Render's API — database, service, environment, first deploy — reading its
> credentials from the environment. See [`SERVICES.md`](SERVICES.md) for the
> individual providers and [`PRODUCTION.md`](PRODUCTION.md) for what actually
> runs once it is up.

> **Secrets go in the host's dashboard, never in chat, never in git.** That
> includes the two `BOOTSTRAP_*` passwords. Any password that has ever been
> pasted into a chat or a commit should be changed after first login.

---

## Path A — Render + Neon (recommended, ~15 minutes)

The repo carries a `Dockerfile` (web server + queue worker + scheduler in one
container, which is what a free tier gives you) and a `render.yaml` blueprint
that wires everything except the secrets.

1. **Database.** Create a free project at [neon.tech](https://neon.tech)
   (free tier: 0.5 GB, no card). Copy the **connection string** — it looks like
   `postgresql://user:pass@ep-xxx.eu-central-1.aws.neon.tech/neondb?sslmode=require`.

2. **App.** At [render.com](https://render.com) → *New → Blueprint*, point it
   at this GitHub repository. Render reads `render.yaml` and creates the
   service.

3. **Secrets.** In the service's *Environment* tab add:

   | Key | Value |
   |---|---|
   | `APP_KEY` | run `php artisan key:generate --show` locally, or use Render's "Generate" |
   | `DATABASE_URL` | the Neon connection string |
   | `BOOTSTRAP_OWNER_EMAIL` | your everyday account's email |
   | `BOOTSTRAP_OWNER_PASSWORD` | its password |
   | `BOOTSTRAP_ADMIN_EMAIL` | the admin account's email |
   | `BOOTSTRAP_ADMIN_PASSWORD` | its password |

4. **Deploy.** Render builds the Dockerfile and boots it. On boot the container
   runs migrations, seeds the public demo account, and creates the two
   operator accounts above — owner with indefinite complimentary access, admin
   with the same plus the admin flag. Existing accounts are never overwritten.

5. The public URL is on the service page: `https://hevy-analytics-xxxx.onrender.com`.

**Free-tier honesty:**
- The service **sleeps after ~15 minutes idle** and takes ~30 s to wake. Fine
  for testing; annoying for daily use.
- **The scheduler sleeps with it.** Hourly syncs, trial emails and the weekly
  demo refresh only run while the container is awake — they catch up on the
  next visit. For testing that is fine; for real users you would move to a
  paid instance (or an external cron pinging the app) before it matters.
- The container's **disk is ephemeral**: progress photos vanish on each deploy.
  Everything in Postgres (which is everything else) survives.
- Mail is set to `log`, so no email leaves the server; that is why
  `AUTO_VERIFY_EMAIL=true` is preset. For real email, create a free
  [resend.com](https://resend.com) account (3 000 mails/month), set the
  `MAIL_*` variables, and turn `AUTO_VERIFY_EMAIL` off.

## Path B — Laravel Cloud (sandbox tier)

[cloud.laravel.com](https://cloud.laravel.com) → connect the GitHub repo →
pick the free sandbox tier. It detects Laravel, provisions Postgres, and runs
deploys on push; no Dockerfile involved. Add the same environment variables as
above, plus a queue worker and the scheduler in the dashboard, and set the
deploy command to run `php artisan migrate --force && php artisan app:demo &&
php artisan app:bootstrap-accounts`. Sandbox apps hibernate when idle, like
Render's free tier.

## After either path

1. Sign in with the owner account and **change the password** (Profile).
   Do the same for the admin account.
2. Open `/admin/users` as the admin to confirm the flag works.
3. The landing page's demo button should drop you into the seeded demo.
4. Paddle stays unconfigured until you run `php artisan app:billing-setup`
   on the server (or set the `PADDLE_*` variables) — until then the app runs
   with billing off, exactly as designed.
