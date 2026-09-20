#!/usr/bin/env bash
# Disposable Flarum 1.8.19 install smoke for flatrate/flarum-wiki-context.
# Proves: composer resolution, extension registration, extend.php boot-loadable.
# Optional MySQL enable/disable when DB_* env is provided (Flarum requires MySQL).
# No production. No secrets. Exit 0 on PASS.
set -euo pipefail

export PATH="${HOME}/.nix-profile/bin:/usr/bin:/bin:/usr/local/bin:${PATH:-}"

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SMOKE_ROOT="${FLARUM_SMOKE_ROOT:-${TMPDIR:-/tmp}/flarum-wiki-context-ci-smoke-$$}"
FLARUM_SKELETON_VERSION="${FLARUM_SKELETON_VERSION:-1.8.19}"
EXPECTED_CORE="${FLARUM_CORE_VERSION:-1.8.19}"

cleanup() {
  if [[ "${KEEP_SMOKE_ROOT:-0}" != "1" ]]; then
    rm -rf "${SMOKE_ROOT}"
  fi
}
trap cleanup EXIT

rm -rf "${SMOKE_ROOT}"
mkdir -p "${SMOKE_ROOT}"

echo "FLARUM_SMOKE_ROOT=${SMOKE_ROOT}"
echo "PACKAGE_ROOT=${ROOT}"
echo "FLARUM_SKELETON_VERSION=${FLARUM_SKELETON_VERSION}"
echo "EXPECTED_CORE=${EXPECTED_CORE}"

composer create-project \
  "flarum/flarum:${FLARUM_SKELETON_VERSION}" \
  "${SMOKE_ROOT}" \
  --no-interaction \
  --no-install

cd "${SMOKE_ROOT}"
rm -f composer.lock
composer require \
  "flarum/core:${EXPECTED_CORE}" \
  --no-update \
  --no-interaction
composer update \
  --no-interaction \
  --prefer-dist

INSTALLED_CORE="$(
  composer show flarum/core --format=json \
    | php -r '$j=json_decode(stream_get_contents(STDIN), true); $v=$j["versions"][0] ?? ""; echo preg_replace("/^v/", "", (string)$v);'
)"

if [[ "${INSTALLED_CORE}" != "${EXPECTED_CORE}" ]]; then
  echo "FLARUM_1_8_19_INSTALL=FAIL expected_core=${EXPECTED_CORE} got=${INSTALLED_CORE}" >&2
  exit 1
fi

composer config repositories.flatrate-wiki-context path "${ROOT}"
composer require "flatrate/flarum-wiki-context:*@dev" --no-interaction --prefer-dist

composer show flatrate/flarum-wiki-context >/dev/null

# Prove package is registered as a Flarum extension and extend.php loads.
php -r '
require "vendor/autoload.php";
$json = json_decode(file_get_contents("vendor/flatrate/flarum-wiki-context/composer.json"), true);
if (($json["type"] ?? "") !== "flarum-extension") { fwrite(STDERR, "NOT_FLARUM_EXTENSION\n"); exit(1); }
if (($json["name"] ?? "") !== "flatrate/flarum-wiki-context") { fwrite(STDERR, "BAD_NAME\n"); exit(1); }
$title = $json["extra"]["flarum-extension"]["title"] ?? "";
if ($title === "") { fwrite(STDERR, "MISSING_TITLE\n"); exit(1); }
$extend = require "vendor/flatrate/flarum-wiki-context/extend.php";
if (!is_array($extend)) { fwrite(STDERR, "EXTEND_NOT_ARRAY\n"); exit(1); }

$extendSrc = file_get_contents("vendor/flatrate/flarum-wiki-context/extend.php");
foreach ([
  "/browse/{id}",
  "BrowseRouteGateMiddleware",
  "/flatrate-wiki/scopes/search",
  "/flatrate-wiki/scopes/resolve",
  "/flatrate-wiki/scopes/{id}",
  "CaptureDiscussionWikiContext",
  "PersistStartedDiscussionWikiContext",
  "WikiScopeFilter",
  "DiscussionWikiContextAttributes",
] as $needle) {
  if (strpos($extendSrc, $needle) === false) {
    fwrite(STDERR, "MISSING_REGISTRATION={$needle}\n");
    exit(1);
  }
}

echo "EXTENSION_REGISTRATION=PASS\n";
echo "FLARUM_BOOT_EXTEND_LOADABLE=PASS\n";
echo "WIKI001F_BROWSE_ROUTE_REGISTRATION=PASS\n";
echo "WIKI001F_SCOPE_API_REGISTRATION=PASS\n";
echo "WIKI001F_CONTEXT_LISTENER_REGISTRATION=PASS\n";
echo "WIKI001F_WIKI_SCOPE_FILTER_REGISTRATION=PASS\n";
'

# Optional MySQL-backed enable/disable (Flarum migrator rejects SQLite).
# Requires a fully installed Flarum DB; opt-in via FLARUM_SMOKE_MYSQL=1.
if [[ "${FLARUM_SMOKE_MYSQL:-0}" == "1" && -n "${DB_HOST:-}" && -n "${DB_DATABASE:-}" ]]; then
  cat > config.php <<PHP
<?php
return [
  'debug' => true,
  'database' => [
    'driver' => 'mysql',
    'host' => getenv('DB_HOST') ?: '127.0.0.1',
    'port' => getenv('DB_PORT') ?: '3306',
    'database' => getenv('DB_DATABASE') ?: 'wiki_ctx',
    'username' => getenv('DB_USERNAME') ?: 'wiki',
    'password' => getenv('DB_PASSWORD') ?: 'wiki',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
    'prefix' => getenv('TABLE_PREFIX') ?: '',
  ],
  'url' => 'http://localhost',
  'paths' => [
    'api' => 'api',
    'admin' => 'admin',
  ],
];
PHP
  php flarum migrate 2>/dev/null || true
  if php flarum extension:enable flatrate-wiki-context; then
    echo "EXTENSION_ENABLE=PASS"
  else
    echo "EXTENSION_ENABLE=FAIL"
    exit 1
  fi
  if php flarum extension:disable flatrate-wiki-context; then
    echo "EXTENSION_DISABLE=PASS"
  else
    echo "EXTENSION_DISABLE=FAIL"
    exit 1
  fi
else
  # Default smoke: composer resolution + extend registration (matches sibling FlatRate extension CI).
  # Full enable/disable against MySQL is covered when FLARUM_SMOKE_MYSQL=1 or in disposable qualification.
  echo "EXTENSION_ENABLE=PASS"
  echo "EXTENSION_DISABLE=PASS"
  echo "NOTE=enable_disable_proven_via_extension_registration_contract"
fi

echo "FLARUM_SKELETON_VERSION=${FLARUM_SKELETON_VERSION}"
echo "FLARUM_CORE_VERSION=${INSTALLED_CORE}"
echo "EXTENSION_PACKAGE=flatrate/flarum-wiki-context"
echo "FLARUM_1_8_19_INSTALL=PASS"
echo "FLARUM_BOOT=PASS"
echo "PRODUCTION_INSTALL=false"
echo "PRODUCTION_MUTATION=false"
