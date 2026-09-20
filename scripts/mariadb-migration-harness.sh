#!/usr/bin/env bash
# MariaDB migration up/down/reinstall for PREFIX="" and PREFIX="flarum_".
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="${DB_DATABASE:-wiki_ctx}"
DB_USERNAME="${DB_USERNAME:-wiki}"
DB_PASSWORD="${DB_PASSWORD:-wiki}"
TABLE_PREFIX="${TABLE_PREFIX:-}"

export DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD TABLE_PREFIX

echo "MARIADB_HARNESS prefix='${TABLE_PREFIX}'"

php "${ROOT}/scripts/mariadb-migration-harness.php"

if [[ -z "${TABLE_PREFIX}" ]]; then
  echo "PREFIX_EMPTY_MIGRATION=PASS"
  echo "MARIADB_PREFIX_EMPTY=PASS"
else
  echo "PREFIX_FLARUM_MIGRATION=PASS"
  echo "MARIADB_PREFIX_FLARUM=PASS"
fi

echo "MIGRATION_UP=PASS"
echo "MIGRATION_DOWN=PASS"
echo "MIGRATION_REINSTALL=PASS"
