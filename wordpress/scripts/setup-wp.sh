#!/usr/bin/env bash
# =============================================================================
# Idempotent provisioning script for the NovaTech Solutions WordPress environment
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
WP_DIR="$(dirname "$SCRIPT_DIR")"

cd "$WP_DIR"

if [ ! -f .env ]; then
  echo "[setup] Creating .env from .env.example..."
  cp .env.example .env
fi

# shellcheck disable=SC1091
source .env

SITE_URL="${WP_SITE_URL:-http://localhost:8001}"
SITE_TITLE="${WP_SITE_TITLE:-NovaTech Solutions}"
ADMIN_USER="${WP_ADMIN_USER:-lab_admin}"
ADMIN_PASS="${WP_ADMIN_PASSWORD:-CHANGE_ME_LOCAL_DEV_ONLY}"
ADMIN_EMAIL="${WP_ADMIN_EMAIL:-admin@novatech.local}"

echo "[1/6] Starting Docker containers (db, wordpress, mailpit)..."
docker compose up -d db wordpress mailpit

echo "[2/6] Waiting for WordPress container and database readiness..."
for i in $(seq 1 30); do
  if docker compose --profile cli run --rm -T wpcli wp db check --path=/var/www/html >/dev/null 2>&1; then
    break
  fi
  sleep 2
done

echo "[3/6] Ensuring WordPress Core is installed (en_US)..."
if ! docker compose --profile cli run --rm -T wpcli wp core is-installed --path=/var/www/html >/dev/null 2>&1; then
  docker compose --profile cli run --rm -T wpcli wp core install \
    --path=/var/www/html \
    --url="$SITE_URL" \
    --title="$SITE_TITLE" \
    --admin_user="$ADMIN_USER" \
    --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" \
    --skip-email
fi
docker compose --profile cli run --rm -T wpcli wp option update WPLANG "" --path=/var/www/html
docker compose --profile cli run --rm -T wpcli wp option update blogname "NovaTech Solutions" --path=/var/www/html
docker compose --profile cli run --rm -T wpcli wp option update blogdescription "Enterprise Cloud, Data & Platform Engineering" --path=/var/www/html

echo "[4/6] Installing and activating ACF Free and Contact Form 7..."
docker compose --profile cli run --rm -T wpcli wp plugin install advanced-custom-fields --version=6.4.3 --activate --path=/var/www/html
docker compose --profile cli run --rm -T wpcli wp plugin install contact-form-7 --version=6.0.6 --activate --path=/var/www/html

echo "[5/6] Activating ai-hub-theme and configuring permalinks..."
docker compose --profile cli run --rm -T wpcli wp theme activate ai-hub-theme --path=/var/www/html
docker compose --profile cli run --rm -T wpcli wp rewrite structure '/%postname%/' --hard --path=/var/www/html
docker compose --profile cli run --rm -T wpcli wp rewrite flush --hard --path=/var/www/html

echo "[6/6] Running idempotent NovaTech Solutions seed script..."
docker compose --profile cli run --rm -T wpcli wp eval-file /var/www/html/wp-content/scripts/seed-novatech.php --path=/var/www/html

echo "[DONE] NovaTech Solutions WordPress environment is ready!"
echo "  Landing Page : $SITE_URL/novatech-solutions/"
echo "  WP Admin     : $SITE_URL/wp-admin/"
echo "  Mailpit UI   : http://127.0.0.1:8025"
