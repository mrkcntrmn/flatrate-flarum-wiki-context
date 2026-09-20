import { extend } from 'flarum/common/extend';
import DiscussionHero from 'flarum/forum/components/DiscussionHero';
import DiscussionListItem from 'flarum/forum/components/DiscussionListItem';
import app from 'flarum/forum/app';

import WikiDiscussionContextDisplay from './components/WikiDiscussionContextDisplay';

function contextReadEnabled() {
  return Boolean(
    app.forum.attribute('flatRateWikiPublicRolloutEnabled') &&
      (app.forum.attribute('flatRateWikiBrowseRoutesEnabled') ||
        app.forum.attribute('flatRateWikiContextWritesEnabled') ||
        app.forum.attribute('flatRateWikiDerivedFeedsEnabled') ||
        app.forum.attribute('flatRateWikiBrandRootDerivedFeedsEnabled'))
  );
}

/**
 * Public-safe semantic context on discussion pages and feed rows.
 */
export default function addWikiDiscussionPresentation() {
  extend(DiscussionHero.prototype, 'items', function (items) {
    if (!contextReadEnabled()) {
      return;
    }

    const discussion = this.attrs.discussion;
    if (!discussion || !discussion.attribute('flatRateWikiContext')) {
      return;
    }

    items.add(
      'flatRateWikiContext',
      <WikiDiscussionContextDisplay discussion={discussion} compact={false} />,
      5
    );
  });

  extend(DiscussionListItem.prototype, 'infoItems', function (items) {
    if (!contextReadEnabled()) {
      return;
    }

    const discussion = this.attrs.discussion;
    if (!discussion || !discussion.attribute('flatRateWikiContext')) {
      return;
    }

    items.add(
      'flatRateWikiContext',
      <WikiDiscussionContextDisplay discussion={discussion} compact={true} />,
      50
    );
  });
}
