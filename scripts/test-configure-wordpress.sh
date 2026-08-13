#!/usr/bin/env bash
set -euo pipefail

project_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
configure_script="$project_dir/scripts/configure-wordpress.php"

grep -Fq "deactivate_plugins('e-commerce-data-interchange/e-commerce-data-interchange.php', true);" "$configure_script"

if grep -Fq "deactivate_plugins('e-commerce-data-interchange/e-commerce-data-interchange.php');" "$configure_script"; then
  echo "Legacy EDI must be deactivated silently because its deactivation hook can fatal" >&2
  exit 1
fi

echo "configure-wordpress legacy EDI deactivation verified"
