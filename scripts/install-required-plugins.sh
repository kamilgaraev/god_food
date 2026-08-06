#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
wp_cli="$(mktemp)"
trap 'rm -f "$wp_cli"' EXIT

cd "$project_dir"
curl -fsSL https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar -o "$wp_cli"
wordpress_container="$(docker compose ps -q wordpress)"
docker cp "$wp_cli" "${wordpress_container}:/tmp/wp-cli.phar"

docker compose exec -T -e HTTP_HOST=localhost wordpress \
  php /tmp/wp-cli.phar plugin install yookassa \
  --version=2.16.3 \
  --activate \
  --allow-root

docker compose exec -T -e HTTP_HOST=localhost wordpress \
  php /tmp/wp-cli.phar plugin get yookassa \
  --fields=name,status,version \
  --format=table \
  --allow-root
