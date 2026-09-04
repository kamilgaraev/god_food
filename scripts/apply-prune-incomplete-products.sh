#!/usr/bin/env bash
set -euo pipefail
umask 077
cd "$(git rev-parse --show-toplevel)"
revision="${CATALOG_REVISION:-FETCH_HEAD}"
import_dir="$(mktemp -d)"
trap 'rm -rf -- "$import_dir"' EXIT
git archive "$revision" scripts/prune-incomplete-products.php scripts/verify-prune-incomplete-products.php | tar -x -C "$import_dir"
container_dir="/tmp/theobroma-prune-$(date +%s)-$$"
docker compose exec -T --interactive=false wordpress mkdir -p "$container_dir"
for script in prune-incomplete-products.php verify-prune-incomplete-products.php; do
    docker compose cp "$import_dir/scripts/$script" "wordpress:$container_dir/$script"
done
docker compose exec -T --interactive=false wordpress php "$container_dir/verify-prune-incomplete-products.php"
docker compose exec -T --interactive=false wordpress php "$container_dir/prune-incomplete-products.php"
backup_dir="${CATALOG_BACKUP_DIR:-$HOME/theobroma-backups}"
mkdir -p "$backup_dir"
backup="$backup_dir/before-prune-$(date +%Y%m%d-%H%M%S)-$$.sql"
docker compose exec -T --interactive=false db sh -c 'exec mysqldump --single-transaction --no-tablespaces -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' > "$backup.partial"
test -s "$backup.partial"
mv "$backup.partial" "$backup"
printf 'Database backup: %s\n' "$backup"
docker compose exec -T --interactive=false wordpress php "$container_dir/prune-incomplete-products.php" --apply
# Exit nonzero if any published candidate remains. A second run changes nothing.
docker compose exec -T --interactive=false wordpress php "$container_dir/prune-incomplete-products.php" --verify
docker compose exec -T --interactive=false wordpress rm -f "$container_dir/prune-incomplete-products.php" "$container_dir/verify-prune-incomplete-products.php"
docker compose exec -T --interactive=false wordpress rmdir "$container_dir"
printf 'Incomplete products unpublished; protected products unchanged.\n'
