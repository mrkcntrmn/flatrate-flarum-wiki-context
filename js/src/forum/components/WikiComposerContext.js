import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import app from 'flarum/forum/app';

import WikiRelevancePickerModal from './WikiRelevancePickerModal';
import { formatBreadcrumbTrail, relevanceMaxActive } from '../utils/scopeCache';

/**
 * Compact composer context chrome: primary trail + secondary relevance.
 */
export default class WikiComposerContext extends Component {
  view() {
    const state = this.attrs.wikiState;
    if (!state || !state.primaryScopeId) {
      return null;
    }

    const trail = formatBreadcrumbTrail(
      (state.breadcrumbLabels || []).map((label) => ({ label }))
    );
    const selected = state.relevanceScopes || [];
    const max = relevanceMaxActive();

    return (
      <div className="WikiComposerContext" aria-label={app.translator.trans('flatrate-wiki-context.forum.composer.context_label')}>
        <div className="WikiComposerContext-primary">
          <span className="WikiComposerContext-primaryLabel">
            {app.translator.trans('flatrate-wiki-context.forum.composer.posting_under')}
          </span>
          <strong className="WikiComposerContext-trail">{trail}</strong>
        </div>

        <div className="WikiComposerContext-relevance">
          <span className="WikiComposerContext-relevanceLabel">
            {app.translator.trans('flatrate-wiki-context.forum.composer.relevant_to')}
          </span>

          <ul className="WikiComposerContext-relevanceList">
            {selected.map((scope) => (
              <li key={scope.id} className="WikiComposerContext-relevanceItem">
                <span>{scope.label}</span>
                <Button
                  className="Button Button--link"
                  onclick={() => this.removeRelevance(scope.id)}
                  aria-label={app.translator.trans('flatrate-wiki-context.forum.composer.remove_relevance', {
                    label: scope.label,
                  })}
                >
                  ×
                </Button>
              </li>
            ))}
          </ul>

          {selected.length < max ? (
            <Button className="Button Button--link" onclick={() => this.openPicker()}>
              {app.translator.trans('flatrate-wiki-context.forum.composer.add_relevance')}
            </Button>
          ) : null}

          <span className="WikiComposerContext-relevanceCount" aria-live="polite">
            {app.translator.trans('flatrate-wiki-context.forum.composer.relevance_selected_count', {
              count: selected.length,
              max,
            })}
          </span>
        </div>
      </div>
    );
  }

  openPicker() {
    const state = this.attrs.wikiState;
    const suggestions = (this.attrs.suggestions || []).filter(
      (scope) => scope.id !== state.primaryScopeId && scope.discussionCapable !== false
    );

    app.modal.show(WikiRelevancePickerModal, {
      wikiState: state,
      suggestions,
      onSelect: (scope) => {
        if (typeof this.attrs.onAddRelevance === 'function') {
          this.attrs.onAddRelevance(scope);
        }
      },
    });
  }

  removeRelevance(scopeId) {
    if (typeof this.attrs.onRemoveRelevance === 'function') {
      this.attrs.onRemoveRelevance(scopeId);
    }
  }
}
