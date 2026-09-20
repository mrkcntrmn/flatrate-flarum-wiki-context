import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const ROOT = join(HERE, '../..');

test('extend.php feature gates default closed', () => {
  const extend = readFileSync(join(ROOT, 'extend.php'), 'utf8');
  for (const key of [
    'PROJECTION_SYNC_ENABLED',
    'BROWSE_ROUTES_ENABLED',
    'CONTEXT_WRITES_ENABLED',
    'DERIVED_FEEDS_ENABLED',
    'BRAND_ROOT_DERIVED_FEEDS_ENABLED',
    'ADMIN_GHOST_PREVIEW_ENABLED',
    'PUBLIC_ROLLOUT_ENABLED',
  ]) {
    assert.match(extend, new RegExp(`FeatureGates::${key}, '0'`));
  }
});

test('composer package identity', () => {
  const composer = JSON.parse(readFileSync(join(ROOT, 'composer.json'), 'utf8'));
  assert.equal(composer.name, 'flatrate/flarum-wiki-context');
  assert.equal(composer.type, 'flarum-extension');
  assert.equal(composer.require.php, '^8.1');
  assert.ok(composer.require['flarum/core'].includes('1.8.19'));
  assert.equal(composer.require['flatrate/flarum-forum-navigation'], undefined);
});
