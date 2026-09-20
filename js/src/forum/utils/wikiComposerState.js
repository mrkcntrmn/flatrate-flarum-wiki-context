/**
 * Semantic composer state stored on the composer instance (not DOM-only).
 */
export function createWikiComposerState({
  primaryScopeId,
  activeGraphVersionId,
  owningBoardKey,
  breadcrumbLabels = [],
  relevanceScopeIds = [],
  relevanceScopes = [],
}) {
  return {
    primaryScopeId: String(primaryScopeId || '').toLowerCase(),
    activeGraphVersionId: String(activeGraphVersionId || '').toLowerCase(),
    owningBoardKey: owningBoardKey ? String(owningBoardKey) : null,
    breadcrumbLabels: Array.isArray(breadcrumbLabels) ? breadcrumbLabels.slice() : [],
    relevanceScopeIds: Array.isArray(relevanceScopeIds)
      ? relevanceScopeIds.map((id) => String(id).toLowerCase())
      : [],
    relevanceScopes: Array.isArray(relevanceScopes) ? relevanceScopes.slice() : [],
  };
}

export function wikiComposerPayload(state) {
  if (!state || !state.primaryScopeId || !state.activeGraphVersionId) {
    return null;
  }

  return {
    flatRateWikiContext: {
      primaryScopeId: state.primaryScopeId,
      expectedGraphVersionId: state.activeGraphVersionId,
    },
    flatRateWikiRelevance: {
      scopeId: (state.relevanceScopeIds || []).slice(),
    },
  };
}

export function canAddRelevance(state, scopeId, maxActive) {
  const id = String(scopeId || '').toLowerCase();
  if (!state || !id) {
    return false;
  }
  if (id === state.primaryScopeId) {
    return false;
  }
  if ((state.relevanceScopeIds || []).includes(id)) {
    return false;
  }
  if ((state.relevanceScopeIds || []).length >= maxActive) {
    return false;
  }
  return true;
}
