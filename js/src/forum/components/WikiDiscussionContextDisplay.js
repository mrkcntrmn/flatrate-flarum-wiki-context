import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import app from 'flarum/forum/app';

import { formatBreadcrumbTrail, resolveScopes, scopeBrowseUrl } from '../utils/scopeCache';

/**
 * Compact public-safe discussion context presentation.
 * Feed mode stays short; page mode shows primary trail + relevance.
 */
export default class WikiDiscussionContextDisplay extends Component {
  oninit(vnode) {
    super.oninit(vnode);
    this.primary = null;
    this.relevance = [];
    this.loading = false;
    this.load();
  }

  onbeforeupdate(vnode) {
    const prev = this.attrs.discussion;
    const next = vnode.attrs.discussion;
    if (prev !== next) {
      this.attrs = vnode.attrs;
      this.load();
    }
  }

  async load() {
    const discussion = this.attrs.discussion;
    if (!discussion) {
      return;
    }

    const context = discussion.attribute('flatRateWikiContext');
    const relevance = discussion.attribute('flatRateWikiRelevance') || {};
    const primaryId = context && context.primaryScopeId;
    const relevanceIds = (relevance.scopeId || []).slice();

    if (!primaryId && !relevanceIds.length) {
      this.primary = null;
      this.relevance = [];
      return;
    }

    this.loading = true;
    const ids = [primaryId, ...relevanceIds].filter(Boolean);
    const scopes = await resolveScopes(ids);
    const byId = Object.fromEntries(scopes.map((scope) => [scope.id, scope]));

    this.primary = primaryId ? byId[String(primaryId).toLowerCase()] || null : null;
    this.relevance = relevanceIds
      .map((id) => byId[String(id).toLowerCase()] || null)
      .filter(Boolean);
    this.loading = false;
    m.redraw();
  }

  view() {
    if (this.loading && !this.primary && !this.relevance.length) {
      return null;
    }

    if (!this.primary && !this.relevance.length) {
      return null;
    }

    if (this.attrs.compact) {
      return this.feedView();
    }

    return this.pageView();
  }

  pageView() {
    return (
      <div className="WikiDiscussionContext WikiDiscussionContext--page" aria-label={app.translator.trans('flatrate-wiki-context.forum.context.section_label')}>
        {this.primary ? (
          <div className="WikiDiscussionContext-primary">
            <Link href={scopeBrowseUrl(this.primary)} className="WikiDiscussionContext-primaryLink">
              {this.primary.label}
            </Link>
          </div>
        ) : null}

        {this.relevance.length ? (
          <div className="WikiDiscussionContext-relevance">
            <span className="WikiDiscussionContext-relevanceLabel">
              {app.translator.trans('flatrate-wiki-context.forum.context.relevant_to')}
            </span>
            <ul className="WikiDiscussionContext-relevanceList">
              {this.relevance.map((scope) => (
                <li key={scope.id}>
                  <Link href={scopeBrowseUrl(scope)}>{scope.label}</Link>
                </li>
              ))}
            </ul>
          </div>
        ) : null}
      </div>
    );
  }

  feedView() {
    // Short cross-branch explanation — not a giant taxonomy trail.
    const primaryLabel = this.primary ? this.primary.label : null;
    const relevanceLabels = this.relevance.map((scope) => scope.label).filter(Boolean);

    // Avoid redundant "Primary Toyota / Relevant to Toyota" style duplication.
    const filteredRelevance = relevanceLabels.filter(
      (label) => !primaryLabel || label.toLowerCase() !== primaryLabel.toLowerCase()
    );

    return (
      <span className="WikiDiscussionContext WikiDiscussionContext--feed">
        {primaryLabel ? <span className="WikiDiscussionContext-feedPrimary">{primaryLabel}</span> : null}
        {filteredRelevance.length ? (
          <span className="WikiDiscussionContext-feedRelevance">
            {app.translator.trans('flatrate-wiki-context.forum.context.relevant_to_short', {
              labels: filteredRelevance.join(', '),
            })}
          </span>
        ) : null}
      </span>
    );
  }
}

export { formatBreadcrumbTrail };
