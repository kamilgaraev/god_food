#!/bin/sh
# Server runtime includes isolated networks and credentials; never replace it with defaults.
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
cd "$root"
if [ -f compose.runtime.json ]; then
    exec docker compose -f compose.runtime.json "$@"
fi
exec docker compose "$@"
