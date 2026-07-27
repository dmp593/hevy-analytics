#!/bin/sh
set -e

# One container, three processes: web server, queue worker, scheduler.
# Not how a load-balanced production runs — exactly how a free-tier single
# container has to run, because free tiers bill background workers separately.

cd /app

# The release steps. All idempotent, so every boot may run them:
#   migrate             — applies anything new, does nothing otherwise
#   app:demo            — seeds the public demo only if it is missing (free
#                         tiers restart the container on every wake, and a
#                         full reseed would hold each cold start hostage;
#                         the weekly schedule does the refreshing)
#   bootstrap-accounts  — creates the operator accounts from BOOTSTRAP_* env
#                         vars, and never touches an account that exists
# One-shot migration between database hosts.
#
# Set MIGRATE_FROM_DATABASE_URL to the OLD database and redeploy; the contents
# are copied into $DATABASE_URL and the variable can then be removed. Guarded
# by "does the target already have a users table", so a redeploy that forgets
# to remove the variable cannot overwrite live data with a stale snapshot —
# the guard, not the operator's memory, is what makes this safe.
if [ -n "${MIGRATE_FROM_DATABASE_URL:-}" ]; then
    if psql "$DATABASE_URL" -tAc "select to_regclass('public.users')" | grep -q users; then
        echo "==> Target database already populated — skipping host migration."
    else
        echo "==> Copying $MIGRATE_FROM_DATABASE_URL into the new database…"
        pg_dump --no-owner --no-acl --no-comments "$MIGRATE_FROM_DATABASE_URL" \
            | psql --quiet --set ON_ERROR_STOP=1 "$DATABASE_URL"
        echo "==> Copy finished."
    fi
fi

php artisan migrate --force
php artisan app:demo --missing-only || true
php artisan app:bootstrap-accounts || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Both loops restart their process if it dies — there is no supervisor here to
# do it. A silently dead worker is the "sync stuck at queued" bug; a silently
# dead scheduler is trial emails that never go out.
(while true; do
    php artisan queue:work --tries=3 --max-time=3600 || true
    sleep 2
done) &

(while true; do
    php artisan schedule:work || true
    sleep 2
done) &

exec frankenphp php-server --root /app/public --listen ":${PORT:-8080}"
