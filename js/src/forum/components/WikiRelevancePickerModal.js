import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import app from 'flarum/forum/app';

import { canAddRelevance } from '../utils/wikiComposerState';
import { relevanceMaxActive, searchScopes } from '../utils/scopeCache';

/**
 * Bounded Relevant-to picker. Does not render the whole graph.
 */
export default class WikiRelevancePickerModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);

    this.wikiState = this.attrs.wikiState;
    this.onSelect = this.attrs.onSelect || (() => {});
    this.query = '';
    this.results = [];
    this.loading = false;
    this.searchGeneration = 0;
    this.searchTimer = null;
    this.error = null;
  }

  className() {
    return 'WikiRelevancePickerModal Modal--small';
  }

  title() {
    return app.translator.trans('flatrate-wiki-context.forum.composer.relevance_picker_title');
  }

  onremove(vnode) {
    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }
    super.onremove(vnode);
  }

  content() {
    const max = relevanceMaxActive();
    const selectedCount = (this.wikiState.relevanceScopeIds || []).length;
    const suggestions = (this.attrs.suggestions || []).filter((scope) =>
      canAddRelevance(this.wikiState, scope.id, max)
    );

    return (
      <div className="Modal-body">
        <p className="WikiRelevancePickerModal-count" aria-live="polite">
          {app.translator.trans('flatrate-wiki-context.forum.composer.relevance_selected_count', {
            count: selectedCount,
            max,
          })}
        </p>

        <label className="WikiRelevancePickerModal-searchLabel">
          <span className="sr-only">
            {app.translator.trans('flatrate-wiki-context.forum.composer.relevance_search_label')}
          </span>
          <input
            className="FormControl"
            type="search"
            maxlength="64"
            value={this.query}
            placeholder={app.translator.trans('flatrate-wiki-context.forum.composer.relevance_search_placeholder')}
            oninput={(event) => this.onSearchInput(event.target.value)}
          />
        </label>

        {this.error ? <p className="WikiRelevancePickerModal-error">{this.error}</p> : null}

        {!this.query.trim() && suggestions.length ? (
          <div className="WikiRelevancePickerModal-section">
            <h3 className="WikiRelevancePickerModal-heading">
              {app.translator.trans('flatrate-wiki-context.forum.composer.relevance_suggestions')}
            </h3>
            <ul className="WikiRelevancePickerModal-list">{suggestions.map((scope) => this.option(scope))}</ul>
          </div>
        ) : null}

        {this.loading ? <LoadingIndicator /> : null}

        {!this.loading && this.query.trim() ? (
          <div className="WikiRelevancePickerModal-section">
            <h3 className="WikiRelevancePickerModal-heading">
              {app.translator.trans('flatrate-wiki-context.forum.composer.relevance_results')}
            </h3>
            {this.results.length ? (
              <ul className="WikiRelevancePickerModal-list" aria-live="polite">
                {this.results.map((scope) => this.option(scope))}
              </ul>
            ) : (
              <p className="WikiRelevancePickerModal-empty">
                {app.translator.trans('flatrate-wiki-context.forum.composer.relevance_no_results')}
              </p>
            )}
          </div>
        ) : null}

        {!this.query.trim() && !suggestions.length ? (
          <p className="WikiRelevancePickerModal-hint">
            {app.translator.trans('flatrate-wiki-context.forum.composer.relevance_search_hint')}
          </p>
        ) : null}
      </div>
    );
  }

  option(scope) {
    const max = relevanceMaxActive();
    const allowed = canAddRelevance(this.wikiState, scope.id, max) && scope.discussionCapable !== false;

    return (
      <li key={scope.id}>
        <Button
          className="Button Button--link"
          disabled={!allowed}
          onclick={() => this.select(scope)}
        >
          <span className="WikiRelevancePickerModal-label">{scope.label}</span>
          <span className="WikiRelevancePickerModal-type">{scope.type}</span>
        </Button>
      </li>
    );
  }

  select(scope) {
    const max = relevanceMaxActive();
    if (!canAddRelevance(this.wikiState, scope.id, max) || scope.discussionCapable === false) {
      this.error = app.translator.trans('flatrate-wiki-context.forum.composer.relevance_invalid');
      return;
    }

    this.onSelect(scope);
    this.hide();
  }

  onSearchInput(value) {
    this.query = String(value || '').slice(0, 64);
    this.error = null;

    if (this.searchTimer) {
      clearTimeout(this.searchTimer);
    }

    this.searchTimer = setTimeout(() => {
      this.runSearch();
    }, 200);
  }

  async runSearch() {
    const generation = ++this.searchGeneration;
    const q = this.query.trim();

    if (!q) {
      this.results = [];
      this.loading = false;
      m.redraw();
      return;
    }

    this.loading = true;
    m.redraw();

    try {
      const results = await searchScopes(q, 20);
      if (generation !== this.searchGeneration) {
        return;
      }
      this.results = results.filter((scope) => scope.discussionCapable !== false);
    } catch (error) {
      if (generation !== this.searchGeneration) {
        return;
      }
      this.results = [];
      this.error = app.translator.trans('flatrate-wiki-context.forum.composer.relevance_search_failed');
    } finally {
      if (generation === this.searchGeneration) {
        this.loading = false;
        m.redraw();
      }
    }
  }
}
