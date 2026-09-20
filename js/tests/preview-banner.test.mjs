import test from 'node:test';
import assert from 'node:assert/strict';
import PreviewBanner from '../src/forum/preview/PreviewBanner.js';
import {
  AUDIENCE_PROFILES,
  DEFAULT_AUDIENCE,
  isElevatedAdminVisibilityAllowed,
} from '../src/forum/preview/AudienceSelector.js';

test('preview banner contract', () => {
  const c = PreviewBanner.contract();
  assert.equal(c.title, 'ADMIN GHOST PREVIEW');
  assert.equal(c.notVisibleToMembers, true);
  assert.equal(c.readOnly, true);
  assert.equal(c.authorization, 'server-side');
});

test('audience simulation profiles', () => {
  assert.deepEqual(AUDIENCE_PROFILES, ['guest', 'standard_member']);
  assert.equal(DEFAULT_AUDIENCE, 'standard_member');
  assert.equal(isElevatedAdminVisibilityAllowed(), false);
});
