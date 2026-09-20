import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const index = readFileSync(join(HERE, '../src/forum/index.js'), 'utf8');

test('forum shell registers only the accepted WIKI-001F browse routes', () => {
  assert.match(index, /flatrate-wiki-context/);
  assert.match(index, /server-side middleware remains authoritative/i);
  assert.match(index, /flatrate-wiki\.browse/);
  assert.match(index, /path: '\/browse\/:id'/);
  assert.match(index, /path: '\/browse\/:id\/:slug'/);
  assert.doesNotMatch(index, /adminGhostPreview.*component/i);
});
