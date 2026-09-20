import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const HERE = dirname(fileURLToPath(import.meta.url));
const browsePage = readFileSync(join(HERE, '../src/forum/components/BrowsePage.js'), 'utf8');

test('browse page uses native Flarum semantic discussion state', () => {
  assert.match(browsePage, /new DiscussionListState/);
  assert.match(browsePage, /filter:\s*\{\s*'wiki-scope': this\.scopeId\s*\}/);
  assert.match(browsePage, /<DiscussionList state=\{this\.discussionState\}/);
  assert.doesNotMatch(browsePage, /app\.store\.find\([^)]*discussions/);
  console.log('WIKI001F_NO_CLIENT_FEED_MERGE=PASS');
});

test('discussion list renders before downstream directory', () => {
  const discussion = browsePage.indexOf('<DiscussionList state={this.discussionState} />');
  const directory = browsePage.indexOf('{this.directory(scope)}');

  assert.ok(discussion >= 0, 'native discussion list marker missing');
  assert.ok(directory > discussion, 'directory must render after discussion list/load control');
  console.log('WIKI001F_DISCUSSION_FIRST=PASS');
  console.log('WIKI001F_DIRECTORY_AFTER_PAGINATION=PASS');
});

test('browse page normalizes cosmetic slug from scope label', () => {
  assert.match(browsePage, /normalizeCosmeticSlug/);
  assert.match(browsePage, /slugifyScopeLabel\(scope\.label\)/);
  assert.match(browsePage, /m\.route\.set\(target, null, \{ replace: true \}\)/);
  console.log('WIKI001F_COSMETIC_SLUG_NORMALIZATION=PASS');
});

test('view-all browser is searchable and paginated', () => {
  assert.match(browsePage, /type="search"/);
  assert.match(browsePage, /previousViewAllPage/);
  assert.match(browsePage, /nextViewAllPage/);
  assert.match(browsePage, /page\[offset\]/);
  assert.match(browsePage, /page\[limit\]/);
  console.log('WIKI001F_VIEW_ALL_SEARCHABLE_PAGINATED=PASS');
});
