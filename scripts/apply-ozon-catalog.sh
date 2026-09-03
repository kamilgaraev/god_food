#!/usr/bin/env bash
# Run from the existing server checkout, after fetching the catalog branch.
set -euo pipefail
umask 077

cd "$(git rev-parse --show-toplevel)"
revision="${CATALOG_REVISION:-FETCH_HEAD}"
git rev-parse --verify "${revision}^{commit}" >/dev/null
command -v docker >/dev/null
command -v tar >/dev/null
docker compose exec -T --interactive=false wordpress php -r 'require "/var/www/html/wp-load.php"; if (!class_exists("WC_Product_Simple") || !class_exists("ZipArchive")) { exit(1); }'

import_dir="$(mktemp -d)"
trap 'rm -rf -- "$import_dir"' EXIT
git archive "$revision" \
  scripts/sync-ozon-catalog.php \
  scripts/verify-ozon-catalog.php \
  scripts/data/ozon-products-2026-08-11.xlsx | tar -x -C "$import_dir"

container_dir="/tmp/theobroma-catalog-$(date +%s)-$$"
docker compose exec -T --interactive=false wordpress mkdir -p "$container_dir"
docker compose cp "$import_dir/scripts/sync-ozon-catalog.php" "wordpress:$container_dir/sync-ozon-catalog.php"
docker compose cp "$import_dir/scripts/verify-ozon-catalog.php" "wordpress:$container_dir/verify-ozon-catalog.php"
docker compose cp "$import_dir/scripts/data/ozon-products-2026-08-11.xlsx" "wordpress:$container_dir/products.xlsx"

docker compose exec -T --interactive=false wordpress php "$container_dir/verify-ozon-catalog.php" "$container_dir/products.xlsx"
docker compose exec -T --interactive=false wordpress php "$container_dir/sync-ozon-catalog.php" "$container_dir/products.xlsx"

backup_dir="${CATALOG_BACKUP_DIR:-$HOME/theobroma-backups}"
mkdir -p "$backup_dir"
backup="$backup_dir/before-catalog-$(date +%Y%m%d-%H%M%S)-$$.sql"
docker compose exec -T --interactive=false db sh -c \
  'exec mysqldump --single-transaction --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  > "$backup.partial"
test -s "$backup.partial"
mv "$backup.partial" "$backup"
printf 'Database backup: %s\n' "$backup"

docker compose exec -T --interactive=false wordpress php "$container_dir/sync-ozon-catalog.php" "$container_dir/products.xlsx" --apply
docker compose exec -T --interactive=false wordpress php "$container_dir/verify-ozon-catalog.php" "$container_dir/products.xlsx" --runtime
docker compose exec -T --interactive=false wordpress rm -f "$container_dir/sync-ozon-catalog.php" "$container_dir/verify-ozon-catalog.php" "$container_dir/products.xlsx"
docker compose exec -T --interactive=false wordpress rmdir "$container_dir"
printf 'Catalog updated successfully.\n'
