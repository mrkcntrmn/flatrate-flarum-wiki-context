import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import Link from 'flarum/common/components/Link';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Placeholder from 'flarum/common/components/Placeholder';
import DiscussionList from 'flarum/forum/components/DiscussionList';
import DiscussionListState from 'flarum/forum/states/DiscussionListState';

import {
  directoryCaps,
  moreCount,
  slugifyScopeLabel,
  viewAllCount,
  visibleInlineChildren,
} from '../utils/directoryPolicy';

const VIEW_ALL_PAGE_SIZE_DESKTOP = 24;
const VIEW_ALL_PAGE_SIZE_MOBILE = 18;

export default class BrowsePage extends Page {
  oninit(vnode) {
    super.oninit(vnode);

    this.bodyClass = 'App--flatRateWikiBrowse';
    this.scopeId = String(vnode.attrs.id || '').toLowerCase();
    this.requestedSlug = String(vnode.attrs.slug || '');
    this.scopeDocument = null;
    this.scopeLoading = true;
    this.scopeUnavailable = false;

    this.caps = directoryCaps(typeof window !== 'undefined' ? window.innerWidth : 1024);
    this.inlineChildren = [];
    this.inlineMeta = null;
    this.inlineExpanded = false;

    this.viewAllOpen = false;
    this.viewAllItems = [];
    this.viewAllMeta = null;
    this.viewAllLoading = false;
    this.viewAllQuery = '';
    this.viewAllOffset = 0;
    this.viewAllTimer = null;

    const sort = ['latest', 'top', 'newest', 'oldest'].includes(String(vnode.attrs.sort || ''))
      ? String(vnode.attrs.sort)
      : 'latest';

    this.discussionState = new DiscussionListState({
      filter: { 'wiki-scope': this.scopeId },
      sort,
    });

    this.loadScopeAndInlineDirectory();
    this.discussionState.refresh();
  }

  onremove(vnode) {
    if (this.viewAllTimer) {
      clearTimeout(this.viewAllTimer);
    }

    super.onremove(vnode);
  }

  apiBase() {
    return String(app.forum.attribute('apiUrl') || '').replace(/\/$/, '');
  }

  scopeUrl(scope) {
    return app.route('flatrate-wiki.browse.slug', {
      id: scope.id,
      slug: slugifyScopeLabel(scope.label),
    });
  }

  async loadScopeAndInlineDirectory() {
    this.scopeLoading = true;
    this.scopeUnavailable = false;

    try {
      const scopeDocument = await app.request({
        method: 'GET',
        url: `${this.apiBase()}/flatrate-wiki/scopes/${encodeURIComponent(this.scopeId)}`,
        errorHandler: () => {},
      });

      this.scopeDocument = scopeDocument;
      const scope = this.scope();

      if (!scope) {
        throw new Error('wiki_scope_not_found');
      }

      app.setTitle(scope.label);
      this.normalizeCosmeticSlug(scope);

      const inline = await this.requestChildren(0, this.caps.inlineMax, '');
      this.inlineChildren = this.resourcesToScopes(inline.data || []);
      this.inlineMeta = inline.meta || {};
    } catch (error) {
      this.scopeUnavailable = true;
      this.scopeDocument = null;
      this.inlineChildren = [];
      this.inlineMeta = null;
    } finally {
      this.scopeLoading = false;
      m.redraw();
    }
  }

  requestChildren(offset, limit, query) {
    const params = new URLSearchParams();
    params.set('page[offset]', String(Math.max(0, Number(offset) || 0)));
    params.set('page[limit]', String(Math.max(1, Number(limit) || 1)));

    const normalizedQuery = String(query || '').trim();
    if (normalizedQuery) {
      params.set('q', normalizedQuery);
    }

    return app.request({
      method: 'GET',
      url: `${this.apiBase()}/flatrate-wiki/scopes/${encodeURIComponent(this.scopeId)}/children?${params.toString()}`,
      errorHandler: () => {},
    });
  }

  scope() {
    const resource = this.scopeDocument && this.scopeDocument.data;
    if (!resource || !resource.id) return null;

    return {
      id: resource.id,
      ...(resource.attributes || {}),
    };
  }

  breadcrumbs() {
    return (this.scopeDocument && this.scopeDocument.meta && this.scopeDocument.meta.breadcrumbs) || [];
  }

  catchAll() {
    const summary =
      this.scopeDocument &&
      this.scopeDocument.meta &&
      this.scopeDocument.meta.childrenSummary;

    return (summary && summary.catchAll) || (this.inlineMeta && this.inlineMeta.catchAll) || null;
  }

  totalNormalChildren() {
    const summary =
      this.scopeDocument &&
      this.scopeDocument.meta &&
      this.scopeDocument.meta.childrenSummary;

    if (summary && Number.isFinite(Number(summary.normalActiveCount))) {
      return Number(summary.normalActiveCount);
    }

    return Number((this.inlineMeta && this.inlineMeta.totalNormal) || 0);
  }

  resourcesToScopes(resources) {
    return resources.map((resource) => ({
      id: resource.id,
      ...(resource.attributes || {}),
    }));
  }

  normalizeCosmeticSlug(scope) {
    const canonicalSlug = slugifyScopeLabel(scope.label);

    if (this.requestedSlug === canonicalSlug) {
      return;
    }

    const target = app.route('flatrate-wiki.browse.slug', {
      id: scope.id,
      slug: canonicalSlug,
    });

    m.route.set(target, null, { replace: true });
  }

  openViewAll() {
    this.viewAllOpen = true;
    this.viewAllOffset = 0;
    this.viewAllQuery = '';
    this.loadViewAllPage();
  }

  closeViewAll() {
    this.viewAllOpen = false;
    this.viewAllItems = [];
    this.viewAllMeta = null;
    this.viewAllLoading = false;
    this.viewAllOffset = 0;
    this.viewAllQuery = '';
  }

  viewAllPageSize() {
    return this.caps.initial === 6
      ? VIEW_ALL_PAGE_SIZE_MOBILE
      : VIEW_ALL_PAGE_SIZE_DESKTOP;
  }

  async loadViewAllPage() {
    this.viewAllLoading = true;
    m.redraw();

    try {
      const document = await this.requestChildren(
        this.viewAllOffset,
        this.viewAllPageSize(),
        this.viewAllQuery
      );

      this.viewAllItems = this.resourcesToScopes(document.data || []);
      this.viewAllMeta = document.meta || {};
    } finally {
      this.viewAllLoading = false;
      m.redraw();
    }
  }

  onSearchInput(value) {
    this.viewAllQuery = String(value || '').slice(0, 64);
    this.viewAllOffset = 0;

    if (this.viewAllTimer) {
      clearTimeout(this.viewAllTimer);
    }

    this.viewAllTimer = setTimeout(() => {
      this.loadViewAllPage();
    }, 200);
  }

  previousViewAllPage() {
    this.viewAllOffset = Math.max(0, this.viewAllOffset - this.viewAllPageSize());
    this.loadViewAllPage();
  }

  nextViewAllPage() {
    this.viewAllOffset += this.viewAllPageSize();
    this.loadViewAllPage();
  }

  view() {
    if (this.scopeLoading) {
      return (
        <div className="FlatRateWikiBrowsePage">
          <div className="container FlatRateWikiBrowsePage-container">
            <LoadingIndicator />
          </div>
        </div>
      );
    }

    if (this.scopeUnavailable || !this.scope()) {
      return (
        <div className="FlatRateWikiBrowsePage">
          <div className="container FlatRateWikiBrowsePage-container">
            <Placeholder text={app.translator.trans('flatrate-wiki-context.forum.browse.unavailable')} />
          </div>
        </div>
      );
    }

    const scope = this.scope();

    return (
      <div className="FlatRateWikiBrowsePage">
        <div className="container FlatRateWikiBrowsePage-container">
          {this.hero(scope)}

          <section
            className="FlatRateWikiBrowsePage-discussions"
            aria-labelledby="flatRateWikiDiscussionHeading"
          >
            <h2 id="flatRateWikiDiscussionHeading" className="FlatRateWikiBrowsePage-sectionTitle">
              {app.translator.trans('flatrate-wiki-context.forum.browse.discussions', {
                label: scope.label,
              })}
            </h2>

            <DiscussionList state={this.discussionState} />
          </section>

          {this.directory(scope)}
        </div>
      </div>
    );
  }

  hero(scope) {
    const breadcrumbs = this.breadcrumbs();

    return (
      <header className="FlatRateWikiBrowsePage-header">
        <nav
          className="FlatRateWikiBreadcrumb"
          aria-label={app.translator.trans('flatrate-wiki-context.forum.browse.breadcrumb_label')}
        >
          <Link href={app.route('index')}>
            {app.translator.trans('flatrate-wiki-context.forum.browse.all_discussions')}
          </Link>

          {breadcrumbs.map((crumb, index) => {
            const current = index === breadcrumbs.length - 1;

            return (
              <span className="FlatRateWikiBreadcrumb-item" key={crumb.id}>
                <span className="FlatRateWikiBreadcrumb-separator" aria-hidden="true">
                  ›
                </span>
                {current ? (
                  <span aria-current="page">{crumb.label}</span>
                ) : (
                  <Link href={this.scopeUrl(crumb)}>{crumb.label}</Link>
                )}
              </span>
            );
          })}
        </nav>

        <h1 className="FlatRateWikiBrowsePage-title">{scope.label}</h1>
      </header>
    );
  }

  directory(scope) {
    const totalNormal = this.totalNormalChildren();
    const catchAll = this.catchAll();

    if (totalNormal === 0 && !catchAll) {
      return null;
    }

    const visible = visibleInlineChildren(this.inlineChildren, this.inlineExpanded, this.caps);
    const more = moreCount(totalNormal, this.caps);
    const overflow = viewAllCount(totalNormal, this.caps);

    return (
      <section
        className="FlatRateWikiDirectory"
        aria-labelledby="flatRateWikiDirectoryHeading"
      >
        <div className="FlatRateWikiDirectory-divider" aria-hidden="true" />

        <h2 id="flatRateWikiDirectoryHeading" className="FlatRateWikiBrowsePage-sectionTitle">
          {app.translator.trans('flatrate-wiki-context.forum.browse.browse_scope', {
            label: scope.label,
          })}
        </h2>

        {!this.viewAllOpen ? (
          <div>
            <ul className="FlatRateWikiDirectory-grid">
              {visible.map((child) => this.directoryItem(child))}
              {catchAll ? this.directoryItem(catchAll, true) : null}
            </ul>

            <div className="FlatRateWikiDirectory-actions">
              {!this.inlineExpanded && more > 0 ? (
                <Button
                  className="Button"
                  onclick={() => {
                    this.inlineExpanded = true;
                  }}
                >
                  {app.translator.trans('flatrate-wiki-context.forum.browse.more', {
                    count: more,
                  })}
                </Button>
              ) : null}

              {overflow > 0 ? (
                <Button className="Button" onclick={() => this.openViewAll()}>
                  {app.translator.trans('flatrate-wiki-context.forum.browse.view_all', {
                    count: totalNormal,
                  })}
                </Button>
              ) : null}
            </div>
          </div>
        ) : (
          this.viewAllDirectory(scope, catchAll)
        )}
      </section>
    );
  }

  directoryItem(scope, catchAll = false) {
    return (
      <li
        key={scope.id}
        className={catchAll ? 'FlatRateWikiDirectory-item FlatRateWikiDirectory-item--catchAll' : 'FlatRateWikiDirectory-item'}
      >
        <Link href={this.scopeUrl(scope)} className="FlatRateWikiDirectory-link">
          <span className="FlatRateWikiDirectory-label">{scope.label}</span>
          <span className="FlatRateWikiDirectory-type">{scope.type}</span>
        </Link>
      </li>
    );
  }

  viewAllDirectory(scope, catchAll) {
    const meta = this.viewAllMeta || {};
    const total = Number(meta.totalNormal || 0);
    const hasPrevious = this.viewAllOffset > 0;
    const hasNext = Boolean(meta.hasMore);

    return (
      <div className="FlatRateWikiChildBrowser">
        <div className="FlatRateWikiChildBrowser-toolbar">
          <label className="FlatRateWikiChildBrowser-searchLabel">
            <span className="sr-only">
              {app.translator.trans('flatrate-wiki-context.forum.browse.search_children', {
                label: scope.label,
              })}
            </span>
            <input
              className="FormControl"
              type="search"
              value={this.viewAllQuery}
              maxlength="64"
              placeholder={app.translator.trans('flatrate-wiki-context.forum.browse.search_placeholder')}
              oninput={(event) => this.onSearchInput(event.target.value)}
            />
          </label>

          <Button className="Button Button--link" onclick={() => this.closeViewAll()}>
            {app.translator.trans('flatrate-wiki-context.forum.browse.close_view_all')}
          </Button>
        </div>

        {this.viewAllLoading ? (
          <LoadingIndicator />
        ) : (
          <ul className="FlatRateWikiDirectory-grid">
            {this.viewAllItems.map((child) => this.directoryItem(child))}
            {catchAll ? this.directoryItem(catchAll, true) : null}
          </ul>
        )}

        {!this.viewAllLoading && this.viewAllItems.length === 0 && this.viewAllQuery ? (
          <p className="FlatRateWikiChildBrowser-empty">
            {app.translator.trans('flatrate-wiki-context.forum.browse.no_children_match')}
          </p>
        ) : null}

        <div className="FlatRateWikiChildBrowser-pagination" aria-label={app.translator.trans('flatrate-wiki-context.forum.browse.child_pagination')}>
          <Button className="Button" disabled={!hasPrevious} onclick={() => this.previousViewAllPage()}>
            {app.translator.trans('flatrate-wiki-context.forum.browse.previous')}
          </Button>

          <span className="FlatRateWikiChildBrowser-count" aria-live="polite">
            {app.translator.trans('flatrate-wiki-context.forum.browse.result_count', {
              count: total,
            })}
          </span>

          <Button className="Button" disabled={!hasNext} onclick={() => this.nextViewAllPage()}>
            {app.translator.trans('flatrate-wiki-context.forum.browse.next')}
          </Button>
        </div>
      </div>
    );
  }
}
