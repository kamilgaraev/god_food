#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
test_dir="$(mktemp -d)"
trap 'rm -rf "$test_dir"' EXIT

fake_bin="$test_dir/bin"
docker_log="$test_dir/docker.log"
mkdir -p "$fake_bin"

cat > "$fake_bin/curl" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

while (($#)); do
  if [[ "$1" == "-o" ]]; then
    : > "$2"
    exit 0
  fi
  shift
done

exit 1
EOF

cat > "$fake_bin/docker" <<'EOF'
#!/usr/bin/env bash
set -euo pipefail

if [[ "$*" == "compose ps -q wordpress" ]]; then
  echo "wordpress-container"
  exit 0
fi

printf '%s\n' "$*" >> "$DOCKER_LOG"
EOF

chmod +x "$fake_bin/curl" "$fake_bin/docker"

PATH="$fake_bin:$PATH" DOCKER_LOG="$docker_log" \
  bash "$project_dir/scripts/install-required-plugins.sh"

grep -Fq \
  "compose exec -T -e HTTP_HOST=localhost wordpress php /tmp/wp-cli.phar plugin install yookassa --version=2.16.3 --activate --allow-root" \
  "$docker_log"
if grep -Fq "e-commerce-data-interchange" "$docker_log"; then
  echo "Third-party EDI must not be installed" >&2
  exit 1
fi

echo "install-required-plugins behavior verified"
