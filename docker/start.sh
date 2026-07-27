#!/bin/sh
set -e

# ---------------------------------------------------------------------------
# Container entrypoint: web server + queue worker + scheduler in one process
# tree.
#
# This is a COMPROMISE, and an honest one. The correct production topology is
# three separate services — see docs/PRODUCTION.md — because a queue job and
# an HTTP request should not compete for the same CPU, and because a worker
# that dies should be restarted by something whose job is restarting workers.
# Free hosting tiers give exactly one container, so this script does the
# supervising itself: restart with backoff, and drain on shutdown.
# ---------------------------------------------------------------------------

cd /app

# ---------------------------------------------------------------------------
# One-shot migration between database hosts.
#
# Set MIGRATE_FROM_DATABASE_URL to the OLD database and redeploy; its contents
# are copied into $DATABASE_URL, and the variable is then removed by hand. Use
# the provider's INTERNAL connection string when both live at the same host —
# external endpoints are often not reachable from inside their own network.
#
# By default it refuses to touch a target that already has data — the guard,
# not the operator's memory, is what stops a forgotten variable from restoring
# a stale snapshot over a live database. MIGRATE_REPLACE=true overrides that
# and DROPS the target schema first, which is what a redo needs.
# ---------------------------------------------------------------------------
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
            # An earlier version piped dump straight into restore after
            # dropping the target schema. pg_dump then aborted on a version
            # mismatch, and the result was a live site holding an empty
            # database. Destroy nothing until there is a verified replacement
            # in hand — and never fail the boot over an optional import.
            DUMP=/tmp/migrate-source.sql
            SKIP=""

            echo "==> Dumping the source database…"
            if ! pg_dump --no-owner --no-acl --no-comments --file="$DUMP" "$MIGRATE_FROM_DATABASE_URL"; then
                echo "==> pg_dump FAILED — skipping the import, target untouched." >&2
                SKIP=1
            elif ! grep -q "CREATE TABLE" "$DUMP"; then
                # A dump with no CREATE TABLE is a dump that failed quietly;
                # restoring it would empty the target just as thoroughly.
                echo "==> Dump contains no tables — skipping, target untouched." >&2
                SKIP=1
            fi

            if [ -n "$SKIP" ]; then
                echo "==> Import skipped; continuing with a normal boot."
            else
                echo "==> Dumped $(wc -c < "$DUMP") bytes, $(grep -c 'CREATE TABLE' "$DUMP") tables."

                if [ "$TARGET_HAS_DATA" = "t" ]; then
                    echo "==> MIGRATE_REPLACE=true — dropping the target schema."
                    psql --quiet --set ON_ERROR_STOP=1 "$DATABASE_URL" \
                        -c "drop schema public cascade; create schema public;"
                fi

                echo "==> Restoring into the target…"
                psql --quiet --set ON_ERROR_STOP=1 "$DATABASE_URL" --file="$DUMP"
                echo "==> Copy finished: $(psql "$DATABASE_URL" -tAc 'select count(*) from users') user row(s)."
            fi

            rm -f "$DUMP"
        fi
    fi
fi

# ---------------------------------------------------------------------------
# Release steps. All idempotent, so every boot may run them:
#   migrate             — applies anything new, does nothing otherwise
#   app:demo            — seeds the public demo only if it is missing; a full
#                         reseed on every wake would hold each cold start
#                         hostage, and the weekly schedule does the refreshing
#   bootstrap-accounts  — creates the operator accounts from BOOTSTRAP_* env
#                         vars, and never touches an account that exists
# ---------------------------------------------------------------------------
php artisan migrate --force
php artisan app:demo --missing-only || true
php artisan app:bootstrap-accounts || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

# ---------------------------------------------------------------------------
# Supervision.
#
# Each worker runs in a loop that restarts it, WITH BACKOFF: a process that
# dies instantly — a bad config, an unreachable database — would otherwise be
# respawned twice a second forever, burying the cause in its own log spam.
#
# --max-time recycles the worker hourly, and --max-jobs caps it by work done.
# A long-lived PHP process accumulates memory; a recycled worker is the
# cheapest defence against a slow leak becoming an OOM at 4am.
#
# WORKER_ENABLED=false turns both off, for the split topology where a separate
# service runs them (docs/PRODUCTION.md).
# ---------------------------------------------------------------------------
supervise() {
    name="$1"
    shift
    delay=2

    while :; do
        start=$(date +%s)
        "$@" || true
        ran=$(( $(date +%s) - start ))

        if [ "$ran" -ge 60 ]; then
            # Ran a while, so this was a normal recycle rather than a crash.
            delay=2
        else
            echo "==> $name exited after ${ran}s; restarting in ${delay}s" >&2
            delay=$(( delay * 2 ))
            [ "$delay" -gt 60 ] && delay=60
        fi

        sleep "$delay"
    done
}

if [ "${WORKER_ENABLED:-true}" != "false" ]; then
    supervise "queue worker" php artisan queue:work \
        --tries=3 --backoff=10 --max-time=3600 --max-jobs=500 &
    QUEUE_PID=$!

    supervise "scheduler" php artisan schedule:work &
    SCHEDULER_PID=$!
fi

frankenphp php-server --root /app/public --listen ":${PORT:-8080}" &
WEB_PID=$!

# ---------------------------------------------------------------------------
# Graceful shutdown.
#
# `exec`ing the web server left the workers invisible to the platform: SIGTERM
# reached the server only, and the container teardown then killed a running
# queue job mid-flight — a half-applied Hevy sync, or a routine write that
# never finished. queue:work handles SIGTERM by finishing the job in hand and
# then exiting, which is exactly what is wanted, but only if the signal
# actually reaches it.
#
# The supervisor loops are stopped FIRST so they cannot respawn what is being
# drained.
# ---------------------------------------------------------------------------
drain() {
    echo "==> Shutting down: draining workers…"

    kill -TERM "${QUEUE_PID:-}" "${SCHEDULER_PID:-}" 2>/dev/null || true
    pkill -TERM -f "artisan queue:work" 2>/dev/null || true
    pkill -TERM -f "artisan schedule:work" 2>/dev/null || true

    kill -TERM "$WEB_PID" 2>/dev/null || true
    wait "$WEB_PID" 2>/dev/null || true

    echo "==> Drained."
    exit 0
}

trap drain TERM INT

# Exits when the web server does — the process the platform actually watches.
wait "$WEB_PID"
