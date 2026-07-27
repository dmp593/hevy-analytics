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
# Set MIGRATE_FROM_DATABASE_URL to the OLD database and redeploy; its contents
# are copied into $DATABASE_URL, and the variable is then removed by hand.
#
# By default it refuses to touch a target that already has data — the guard,
# not the operator's memory, is what stops a forgotten variable from restoring
# a stale snapshot over a live database. MIGRATE_REPLACE=true overrides that
# and DROPS the target schema first, which is what a redo needs.
if [ -n "${MIGRATE_FROM_DATABASE_URL:-}" ]; then
    echo "==> Host migration requested."

    if [ -z "${DATABASE_URL:-}" ]; then
        echo "==> DATABASE_URL is empty; nothing to copy into. Skipping." >&2
    else
        TARGET_HAS_DATA=$(psql "$DATABASE_URL" -tAc "select to_regclass('public.users') is not null" 2>/dev/null || echo "error")

        if [ "$TARGET_HAS_DATA" = "error" ]; then
            echo "==> Could not reach the target database. Skipping." >&2
        elif [ "$TARGET_HAS_DATA" = "t" ] && [ "${MIGRATE_REPLACE:-}" != "true" ]; then
            echo "==> Target already populated; set MIGRATE_REPLACE=true to overwrite. Skipping."
        else
            # Dump to a file and CHECK IT before touching the target.
            #
            # The first attempt piped straight into psql after dropping the
            # target schema. pg_dump then aborted on a version mismatch, and
            # the result was a dropped database with nothing to put back — the
            # site went down holding an empty schema. Destroy nothing until
            # there is a verified replacement in hand.
            DUMP=/tmp/migrate-source.sql

            echo "==> Dumping the source database…"
            if ! pg_dump --no-owner --no-acl --no-comments \
                    --file="$DUMP" "$MIGRATE_FROM_DATABASE_URL"; then
                echo "==> pg_dump FAILED — target left untouched." >&2
                rm -f "$DUMP"
                exit 1
            fi

            # A dump that produced no CREATE TABLE is a dump that failed
            # quietly; restoring it would empty the target just as thoroughly.
            if ! grep -q "CREATE TABLE" "$DUMP"; then
                echo "==> Dump contains no tables — target left untouched." >&2
                rm -f "$DUMP"
                exit 1
            fi

            echo "==> Dumped $(wc -c < "$DUMP") bytes, $(grep -c 'CREATE TABLE' "$DUMP") tables."

            if [ "$TARGET_HAS_DATA" = "t" ]; then
                echo "==> MIGRATE_REPLACE=true — dropping the target schema."
                psql --quiet --set ON_ERROR_STOP=1 "$DATABASE_URL" \
                    -c "drop schema public cascade; create schema public;"
            fi

            echo "==> Restoring into the target…"
            psql --quiet --set ON_ERROR_STOP=1 "$DATABASE_URL" --file="$DUMP"
            rm -f "$DUMP"
            echo "==> Copy finished: $(psql "$DATABASE_URL" -tAc 'select count(*) from users') user row(s)."
        fi
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
