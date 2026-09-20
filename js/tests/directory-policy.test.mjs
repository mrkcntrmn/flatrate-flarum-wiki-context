import test from 'node:test';
import assert from 'node:assert/strict';
import {
  DESKTOP_INITIAL,
  DESKTOP_INLINE_MAX,
  MOBILE_INITIAL,
  MOBILE_INLINE_MAX,
  directoryCaps,
  moreCount,
  slugifyScopeLabel,
  viewAllCount,
  visibleInlineChildren,
} from '../src/forum/utils/directoryPolicy.js';

test('directory policy matches accepted desktop/mobile budgets', () => {
  assert.deepEqual(directoryCaps(1200), {
    initial: DESKTOP_INITIAL,
    inlineMax: DESKTOP_INLINE_MAX,
  });
  assert.deepEqual(directoryCaps(390), {
    initial: MOBILE_INITIAL,
    inlineMax: MOBILE_INLINE_MAX,
  });

  assert.equal(DESKTOP_INITIAL, 8);
  assert.equal(DESKTOP_INLINE_MAX, 24);
  assert.equal(MOBILE_INITIAL, 6);
  assert.equal(MOBILE_INLINE_MAX, 18);
});

test('inline expansion never exceeds the accepted ceiling', () => {
  const items = Array.from({ length: 40 }, (_, index) => ({ id: index + 1 }));
  const desktop = directoryCaps(1200);

  assert.equal(visibleInlineChildren(items, false, desktop).length, 8);
  assert.equal(visibleInlineChildren(items, true, desktop).length, 24);
  assert.equal(moreCount(40, desktop), 16);
  assert.equal(viewAllCount(40, desktop), 16);
});

test('cosmetic slug is deterministic and never semantic identity', () => {
  assert.equal(slugifyScopeLabel('Toyota'), 'toyota');
  assert.equal(slugifyScopeLabel('Other / Misc'), 'other-misc');
  assert.equal(slugifyScopeLabel('  Front Brake Pads  '), 'front-brake-pads');
});
