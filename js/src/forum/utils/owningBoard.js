import app from 'flarum/forum/app';

/**
 * Resolve the unique top-level Flarum primary board/tag for an owningBoardKey.
 * Fail closed when zero or multiple matches exist — never guess.
 */
export function resolveOwningBoardTag(owningBoardKey) {
  const key = String(owningBoardKey || '').trim();
  if (!key || !app.store) {
    return null;
  }

  const matches = app.store.all('tags').filter((tag) => {
    try {
      return (
        tag.slug() === key &&
        tag.position() !== null &&
        !tag.isChild() &&
        !tag.parent()
      );
    } catch (error) {
      return false;
    }
  });

  return matches.length === 1 ? matches[0] : null;
}

export function canStartWikiDiscussion(scope, meta) {
  if (!scope || !scope.discussionCapable) {
    return false;
  }

  if (!app.forum.attribute('flatRateWikiContextWritesEnabled')) {
    return false;
  }

  if (!app.forum.attribute('flatRateWikiPublicRolloutEnabled')) {
    return false;
  }

  if (!meta || !meta.activeGraphVersionId) {
    return false;
  }

  if (!resolveOwningBoardTag(scope.owningBoardKey)) {
    return false;
  }

  return true;
}
