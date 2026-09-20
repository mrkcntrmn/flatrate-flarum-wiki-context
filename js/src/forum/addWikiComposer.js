import { extend } from 'flarum/common/extend';
import DiscussionComposer from 'flarum/forum/components/DiscussionComposer';
import app from 'flarum/forum/app';

import WikiComposerContext from './components/WikiComposerContext';
import { canAddRelevance, wikiComposerPayload } from './utils/wikiComposerState';
import { relevanceMaxActive, rememberScope } from './utils/scopeCache';

/**
 * Extend the native Flarum DiscussionComposer with WIKI semantic context.
 * Inspected against Flarum 1.8.19 DiscussionComposer.data()/headerItems()/onsubmit.
 */
export default function addWikiComposer() {
  extend(DiscussionComposer.prototype, 'headerItems', function (items) {
    const wikiState = this.composer.fields.flatRateWiki;
    if (!wikiState || !wikiState.primaryScopeId) {
      return;
    }

    items.add(
      'flatRateWikiContext',
      <WikiComposerContext
        wikiState={wikiState}
        suggestions={this.composer.fields.flatRateWikiSuggestions || []}
        onAddRelevance={(scope) => this.addWikiRelevance(scope)}
        onRemoveRelevance={(scopeId) => this.removeWikiRelevance(scopeId)}
      />,
      80
    );
  });

  extend(DiscussionComposer.prototype, 'data', function (data) {
    const payload = wikiComposerPayload(this.composer.fields.flatRateWiki);
    if (!payload) {
      return;
    }

    data.flatRateWikiContext = payload.flatRateWikiContext;
    data.flatRateWikiRelevance = payload.flatRateWikiRelevance;
  });

  DiscussionComposer.prototype.addWikiRelevance = function (scope) {
    const state = this.composer.fields.flatRateWiki;
    if (!state) {
      return;
    }

    const max = relevanceMaxActive();
    if (!canAddRelevance(state, scope.id, max) || scope.discussionCapable === false) {
      return;
    }

    rememberScope(scope);
    state.relevanceScopeIds = [...(state.relevanceScopeIds || []), String(scope.id).toLowerCase()];
    state.relevanceScopes = [...(state.relevanceScopes || []), scope];
    m.redraw();
  };

  DiscussionComposer.prototype.removeWikiRelevance = function (scopeId) {
    const state = this.composer.fields.flatRateWiki;
    if (!state) {
      return;
    }

    const id = String(scopeId).toLowerCase();
    state.relevanceScopeIds = (state.relevanceScopeIds || []).filter((value) => value !== id);
    state.relevanceScopes = (state.relevanceScopes || []).filter((scope) => scope.id !== id);
    m.redraw();
  };
}
