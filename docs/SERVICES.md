# The four free services

The app runs on a free tier end to end. Each service does one job, and each one
needs an account only you can create — signing up requires your email and
accepting their terms.

| What | Service | Free tier | Status |
|---|---|---|---|
| App hosting | [Render](https://render.com) | 1 web service, sleeps when idle | **live** |
| Database | [Neon](https://neon.tech) | 0.5 GB, no expiry | pending |
| Email | [Resend](https://resend.com) | 3 000/month, 100/day | pending |
| Photo storage | [Cloudflare R2](https://dash.cloudflare.com) | 10 GB, **no egress fees** | pending |

Everything below is already wired in code. Each section is the two-minute part
that has to be done in a browser, plus the environment variables to paste into
Render → your service → **Environment**. A save there triggers a redeploy.

---

## Database — Neon

Render's free Postgres **expires 30 days after creation**. Neon's does not, which
is the whole reason to move.

1. [neon.tech](https://neon.tech) → sign up → new project, region **Frankfurt**
   (next to the app, so queries are not crossing the Atlantic).
2. Copy the **connection string**.

| Variable | Value |
|---|---|
| `DATABASE_URL` | `postgresql://…?sslmode=require` from Neon |

The container migrates on boot and recreates the demo and operator accounts, so
an empty database fills itself. **If there is data worth keeping, dump it first**
(`pg_dump "$OLD_URL" | psql "$NEW_URL"`) — the app cannot re-create your synced
training history.

## Email — Resend

Until this is set, `MAIL_MAILER=log` throws every email into the log, which is
why new accounts are auto-verified. Once mail works, verification can do its job
again.

1. [resend.com](https://resend.com) → sign up.
2. **Domains** → add a domain you own and paste the DNS records it gives you.
   No domain? Skip it and send from `onboarding@resend.dev`, which works
   immediately but only delivers to your own address — fine for testing.
3. **API Keys** → create one with *Sending access*.

| Variable | Value |
|---|---|
| `RESEND_API_KEY` | `re_…` |
| `MAIL_MAILER` | `resend` |
| `MAIL_FROM_ADDRESS` | `no-reply@yourdomain.com`, or `onboarding@resend.dev` |
| `MAIL_FROM_NAME` | `Hevy Analytics` |
| `AUTO_VERIFY_EMAIL` | `false` ← turn it off; this is the point |

Check it with `php artisan app:send-trial-emails --dry-run` in the Render shell:
it lists who would be emailed without sending anything.

## Photo storage — Cloudflare R2

Progress photos are the only files the app cannot re-create, and Render's disk
is wiped on every deploy. R2 rather than S3 for one specific reason: every photo
is **streamed through the app**, never served from a public bucket URL, because
a guessable link to a photograph of someone's body is not access control. That
design makes every view an egress — a growing bill on S3, free on R2.

1. [dash.cloudflare.com](https://dash.cloudflare.com) → **R2** → *Create bucket*
   (a card is required for verification; the free tier is not charged).
   Name it `hevy-analytics-photos`. **Leave it private** — do not enable public
   access.
2. **Manage R2 API Tokens** → *Create API token* → **Object Read & Write**,
   scoped to that bucket.
3. The token page shows an access key, a secret, and an endpoint like
   `https://<account-id>.r2.cloudflarestorage.com`.

| Variable | Value |
|---|---|
| `PHOTO_DISK` | `r2` |
| `R2_ACCESS_KEY_ID` | from the token |
| `R2_SECRET_ACCESS_KEY` | from the token |
| `R2_BUCKET` | `hevy-analytics-photos` |
| `R2_ENDPOINT` | `https://<account-id>.r2.cloudflarestorage.com` |

Photos uploaded before the switch stay on the old container disk and are gone
after the next deploy. Nothing else moves — the database keeps the rows.

---

## Why these, and not the obvious alternatives

- **Cloudinary** is built for public image CDNs with transformations. These
  photos must never be public, so its main feature is one we would have to
  disable, and its free tier is metered on transformations we do not use.
- **AWS S3** free tier lasts twelve months and then charges for egress — which,
  given the streaming design above, is exactly the axis that grows.
- **Supabase Storage** gives 1 GB against R2's 10 GB.
- **Backblaze B2** is a fair second choice: 10 GB free, S3-compatible. Its free
  egress is capped daily rather than unlimited, so R2 wins on the same axis.

---

## FatSecret (nutrition diary sync)

OAuth 1.0 consumer credentials from platform.fatsecret.com (Account &
Settings). OAuth 2.0 exists but requires an IP whitelist with 24 h
propagation — unusable behind shared egress, which is why the app prefers
the 1.0 pair when both are configured.

| Variable | Value |
|---|---|
| `FATSECRET_CONSUMER_KEY` | REST API OAuth 1.0 credentials |
| `FATSECRET_CONSUMER_SECRET` | idem |
| `FATSECRET_CLIENT_ID` / `FATSECRET_CLIENT_SECRET` | OAuth 2.0 pair, parked for a future need |
| `FATSECRET_*_URL` | endpoint overrides; defaults follow the official docs |

Users link on their profile (three-legged OAuth — their password never
touches the app); `fatsecret:sync` re-reads each linked account's last 7
days nightly at 03:30. Tokens are stored with the rotation-tolerant
encrypted cast.
