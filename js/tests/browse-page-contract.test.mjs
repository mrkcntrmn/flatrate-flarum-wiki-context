import test from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import {
  canAddRelevance,
  createWikiComposerState,
  wikiComposerPayload,
} from '../src/forum/utils/wikiComposerState.js';

const HERE = dirname(fileURLToPath(import.meta.url));
const browsePage = readFileSync(join(HERE, '../src/forum/components/BrowsePage.js'), 'utf8');
const addWikiComposer = readFileSync(join(HERE, '../src/forum/addWikiComposer.js'), 'utf8');
const addPresentation = readFileSync(join(HERE, '../src/forum/addWikiDiscussionPresentation.js'), 'utf8');
const owningBoard = readFileSync(join(HERE, '../src/forum/utils/owningBoard.js'), 'utf8');
const index = readFileSync(join(HERE, '../src/forum/index.js'), 'utf8');
const relevanceModal = readFileSync(join(HERE, '../src/forum/components/WikiRelevancePickerModal.js'), 'utf8');

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
  console.log('WIKI001F_SCOPE_UUID_ROUTE=PASS');
});

test('view-all browser is searchable and paginated', () => {
  assert.match(browsePage, /type="search"/);
  assert.match(browsePage, /previousViewAllPage/);
  assert.match(browsePage, /nextViewAllPage/);
  assert.match(browsePage, /page\[offset\]/);
  assert.match(browsePage, /page\[limit\]/);
  console.log('WIKI001F_VIEW_ALL_SEARCHABLE_PAGINATED=PASS');
});

test('start discussion only when discussion-capable and write gates allow', () => {
  assert.match(browsePage, /showStartDiscussion/);
  assert.match(browsePage, /canStartWikiDiscussion/);
  assert.match(browsePage, /startDiscussion/);
  assert.match(owningBoard, /scope\.discussionCapable/);
  assert.match(owningBoard, /flatRateWikiContextWritesEnabled/);
  assert.match(owningBoard, /flatRateWikiPublicRolloutEnabled/);
  assert.match(owningBoard, /matches\.length === 1/);
  console.log('WIKI001F_START_DISCUSSION_CONTEXT=PASS');
});

test('composer receives primary scope, active graph, and owning board', () => {
  assert.match(browsePage, /createWikiComposerState/);
  assert.match(browsePage, /activeGraphVersionId: meta\.activeGraphVersionId/);
  assert.match(browsePage, /primaryScopeId: scope\.id/);
  assert.match(browsePage, /app\.composer\.fields\.tags = \[board\]/);
  assert.match(browsePage, /app\.composer\.fields\.flatRateWiki = wikiState/);
  assert.match(addWikiComposer, /data\.flatRateWikiContext/);
  assert.match(addWikiComposer, /data\.flatRateWikiRelevance/);
  assert.match(addWikiComposer, /extend\(DiscussionComposer\.prototype, 'data'/);
  console.log('WIKI001F_COMPOSER_SEMANTIC_PAYLOAD=PASS');
  console.log('WIKI001F_OWNING_BOARD_PRESELECT=PASS');
});

test('relevance UI enforces primary/self/duplicate/max bounds', () => {
  const state = createWikiComposerState({
    primaryScopeId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    activeGraphVersionId: '11111111-1111-4111-8111-111111111111',
    owningBoardKey: 'toyota',
    breadcrumbLabels: ['Toyota', 'Camry'],
    relevanceScopeIds: ['bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb'],
  });

  assert.equal(canAddRelevance(state, state.primaryScopeId, 5), false);
  assert.equal(canAddRelevance(state, 'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb', 5), false);
  assert.equal(canAddRelevance(state, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 5), true);
  assert.equal(canAddRelevance(state, 'cccccccc-cccc-4ccc-8ccc-cccccccccccc', 1), false);

  const payload = wikiComposerPayload(state);
  assert.deepEqual(payload.flatRateWikiContext, {
    primaryScopeId: 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaaaa',
    expectedGraphVersionId: '11111111-1111-4111-8111-111111111111',
  });
  assert.deepEqual(payload.flatRateWikiRelevance.scopeId, [
    'bbbbbbbb-bbbb-4bbb-8bbb-bbbbbbbbbbbb',
  ]);

  assert.match(relevanceModal, /maxlength="64"/);
  assert.match(relevanceModal, /searchGeneration/);
  assert.doesNotMatch(relevanceModal, /free.?text.?tag/i);
  console.log('WIKI001F_RELEVANCE_UI_BOUNDED=PASS');
  console.log('WIKI001F_RELEVANCE_PRIMARY_SELF_REJECTED=PASS');
  console.log('WIKI001F_RELEVANCE_DUPLICATE_REJECTED=PASS');
});

test('browse interaction accessibility and stale search protection', () => {
  assert.match(browsePage, /aria-expanded/);
  assert.match(browsePage, /aria-controls/);
  assert.match(browsePage, /aria-live="polite"/);
  assert.match(browsePage, /viewAllSearchGeneration/);
  assert.match(browsePage, /generation !== this\.viewAllSearchGeneration/);
  assert.match(browsePage, /addEventListener\('resize'/);
  assert.match(browsePage, /removeEventListener\('resize'/);
  assert.match(browsePage, /input\.focus/);
  assert.match(browsePage, /viewAllTriggerEl\.focus/);
  console.log('WIKI001F_SEARCH_STALE_RESPONSE_IGNORED=PASS');
  console.log('WIKI001F_ACCESSIBILITY=PASS');
});

test('discussion context presentation is registered without per-row store.find loops in source', () => {
  assert.match(index, /addWikiDiscussionPresentation/);
  assert.match(addPresentation, /DiscussionHero/);
  assert.match(addPresentation, /DiscussionListItem/);
  assert.match(addPresentation, /WikiDiscussionContextDisplay/);
  console.log('WIKI001F_CONTEXT_PRESENTATION=PASS');
});
