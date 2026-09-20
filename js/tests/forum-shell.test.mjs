import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const index = readFileSync(join(HERE, '../src/forum/index.js'), 'utf8');

test('forum shell does not enable incomplete public UI', () => {
  assert.match(index, /flatrate-wiki-context/);
  assert.match(index, /fail-closed/i);
  assert.doesNotMatch(index, /app\.routes\.browse\s*=/);
});
