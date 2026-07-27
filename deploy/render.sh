#!/usr/bin/env bash
set -euo pipefail

# Deploys this repository to Render's free tier, end to end, via the API:
# a free Postgres 16 plus a free web service built from the Dockerfile.
#
# Idempotent: resources are found by name before being created, so running it
# twice converges instead of duplicating.
#
# Needs (as ENVIRONMENT variables — never hardcode credentials in a script):
#   RENDER_API_KEY               from dashboard.render.com/settings#api-keys
#   BOOTSTRAP_OWNER_EMAIL        your account
#   BOOTSTRAP_OWNER_PASSWORD
#   BOOTSTRAP_ADMIN_EMAIL        the admin account
#   BOOTSTRAP_ADMIN_PASSWORD
#
# Usage:
#   export RENDER_API_KEY=rnd_...
#   export BOOTSTRAP_OWNER_EMAIL=... BOOTSTRAP_OWNER_PASSWORD=...
#   export BOOTSTRAP_ADMIN_EMAIL=... BOOTSTRAP_ADMIN_PASSWORD=...
#   bash deploy/render.sh

API="https://api.render.com/v1"
REPO_URL="https://github.com/dmp593/hevy-analytics"
NAME="hevy-analytics"
DB_NAME="hevy-analytics-db"
REGION="frankfurt"

for var in RENDER_API_KEY BOOTSTRAP_OWNER_EMAIL BOOTSTRAP_OWNER_PASSWORD BOOTSTRAP_ADMIN_EMAIL BOOTSTRAP_ADMIN_PASSWORD; do
    [ -n "${!var:-}" ] || { echo "Missing \$$var" >&2; exit 1; }
done

req() { # method path [json-body]
    local method="$1" path="$2" body="${3:-}"
    if [ -n "$body" ]; then
        curl -sS -X "$method" "$API$path" \
            -H "Authorization: Bearer $RENDER_API_KEY" \
            -H "Content-Type: application/json" -H "Accept: application/json" \
            -d "$body"
    else
        curl -sS -X "$method" "$API$path" \
            -H "Authorization: Bearer $RENDER_API_KEY" -H "Accept: application/json"
    fi
}

jsonq() { python3 -c "import json,sys;d=json.load(sys.stdin);print(eval(sys.argv[1]))" "$1"; }

echo "==> Owner"
OWNER_ID=$(req GET /owners | jsonq "d[0]['owner']['id']")
echo "    $OWNER_ID"

echo "==> Postgres ($DB_NAME, free tier — NOTE: Render free Postgres expires after 30 days;"
echo "    swap DATABASE_URL for a free neon.tech database when you outgrow testing)"
DB_ID=$(req GET "/postgres?name=$DB_NAME&limit=1" | jsonq "d[0]['postgres']['id'] if d else ''")
if [ -z "$DB_ID" ]; then
    DB_ID=$(req POST /postgres "{
        \"name\": \"$DB_NAME\", \"ownerId\": \"$OWNER_ID\",
        \"version\": \"16\", \"plan\": \"free\", \"region\": \"$REGION\"
    }" | jsonq "d['id']")
    echo "    created $DB_ID"
fi

echo -n "    waiting for the database to be available "
for _ in $(seq 1 60); do
    STATUS=$(req GET "/postgres/$DB_ID" | jsonq "d.get('status','')")
    [ "$STATUS" = "available" ] && break
    echo -n "."; sleep 5
done
echo " $STATUS"
[ "$STATUS" = "available" ] || { echo "Database never became available" >&2; exit 1; }

DATABASE_URL=$(req GET "/postgres/$DB_ID/connection-info" | jsonq "d['internalConnectionString']")

echo "==> APP_KEY"
APP_KEY="base64:$(head -c 32 /dev/urandom | base64)"

env_vars() { # emitted as JSON array
    python3 - "$DATABASE_URL" "$APP_KEY" <<'PY'
import json, os, sys
print(json.dumps([{"key": k, "value": v} for k, v in {
    "APP_ENV": "production",
    "APP_DEBUG": "false",
    "APP_KEY": sys.argv[2],
    "DATABASE_URL": sys.argv[1],
    "DB_CONNECTION": "pgsql",
    "TRUSTED_PROXIES": "*",
    "LOG_CHANNEL": "stderr",
    "SESSION_DRIVER": "database",
    "QUEUE_CONNECTION": "database",
    "CACHE_STORE": "database",
    "MAIL_MAILER": "log",
    "AUTO_VERIFY_EMAIL": "true",
    "BOOTSTRAP_OWNER_EMAIL": os.environ["BOOTSTRAP_OWNER_EMAIL"],
    "BOOTSTRAP_OWNER_PASSWORD": os.environ["BOOTSTRAP_OWNER_PASSWORD"],
    "BOOTSTRAP_ADMIN_EMAIL": os.environ["BOOTSTRAP_ADMIN_EMAIL"],
    "BOOTSTRAP_ADMIN_PASSWORD": os.environ["BOOTSTRAP_ADMIN_PASSWORD"],
}.items()]))
PY
}

echo "==> Web service ($NAME)"
SVC_ID=$(req GET "/services?name=$NAME&limit=1" | jsonq "d[0]['service']['id'] if d else ''")
if [ -z "$SVC_ID" ]; then
    CREATE=$(req POST /services "{
        \"type\": \"web_service\", \"name\": \"$NAME\", \"ownerId\": \"$OWNER_ID\",
        \"repo\": \"$REPO_URL\", \"branch\": \"main\", \"autoDeploy\": \"yes\",
        \"envVars\": $(env_vars),
        \"serviceDetails\": {
            \"plan\": \"free\", \"region\": \"$REGION\", \"runtime\": \"docker\",
            \"healthCheckPath\": \"/up\",
            \"envSpecificDetails\": {\"dockerfilePath\": \"./Dockerfile\"}
        }
    }")
    SVC_ID=$(echo "$CREATE" | jsonq "d['service']['id']") || { echo "$CREATE" >&2; exit 1; }
    echo "    created $SVC_ID"
fi

URL=$(req GET "/services/$SVC_ID" | jsonq "d['serviceDetails']['url']")
echo "==> APP_URL = $URL"
req PUT "/services/$SVC_ID/env-vars/APP_URL" "{\"value\": \"$URL\"}" >/dev/null

echo -n "==> Waiting for the deploy (Docker build takes several minutes) "
for _ in $(seq 1 180); do
    DEPLOY=$(req GET "/services/$SVC_ID/deploys?limit=1" | jsonq "d[0]['deploy']['status'] if d else ''")
    case "$DEPLOY" in
        live) break ;;
        build_failed|update_failed|canceled|deactivated) echo; echo "Deploy ended as: $DEPLOY" >&2; exit 1 ;;
    esac
    echo -n "."; sleep 10
done
echo " $DEPLOY"

echo
echo "======================================================================"
echo "  Public URL:  $URL"
echo "  Demo:        the landing page's demo button (seeded on first boot)"
echo "  Accounts:    created on first boot from the BOOTSTRAP_* variables."
echo "               CHANGE BOTH PASSWORDS after first login, and REGENERATE"
echo "               the Render API key you used for this script."
echo "======================================================================"
