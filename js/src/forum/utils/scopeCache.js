import app from 'flarum/forum/app';

const cache = new Map();

export function rememberScope(scope) {
  if (!scope || !scope.id) {
    return scope;
  }

  const id = String(scope.id).toLowerCase();
  const attrs = scope.attributes || scope;
  const normalized = {
    ...attrs,
    id,
  };

  cache.set(id, normalized);

  return normalized;
}

export function getCachedScope(id) {
  return cache.get(String(id || '').toLowerCase()) || null;
}

function apiBase() {
  return String(app.forum.attribute('apiUrl') || '').replace(/\/$/, '');
}

function resourceToScope(resource) {
  if (!resource || !resource.id) {
    return null;
  }

  return rememberScope({
    id: resource.id,
    ...(resource.attributes || {}),
  });
}

/**
 * Bounded batch resolve with client cache keyed by immutable scope UUID.
 */
export async function resolveScopes(ids) {
  const unique = [];
  for (const raw of ids || []) {
    const id = String(raw || '').toLowerCase();
    if (!/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/.test(id)) {
      continue;
    }
    if (!unique.includes(id)) {
      unique.push(id);
    }
  }

  const missing = unique.filter((id) => !cache.has(id));

  if (missing.length) {
    const params = new URLSearchParams();
    params.set('ids', missing.join(','));

    try {
      const document = await app.request({
        method: 'GET',
        url: `${apiBase()}/flatrate-wiki/scopes/resolve?${params.toString()}`,
        errorHandler: () => {},
      });

      for (const resource of document.data || []) {
        resourceToScope(resource);
      }
    } catch (error) {
      // Fail soft for presentation — rows still render without labels.
    }
  }

  return unique.map((id) => cache.get(id) || null).filter(Boolean);
}

export async function searchScopes(query, limit = 20) {
  const q = String(query || '').trim().slice(0, 64);
  if (!q) {
    return [];
  }

  const params = new URLSearchParams();
  params.set('q', q);
  params.set('page[limit]', String(Math.max(1, Math.min(20, Number(limit) || 20))));

  const document = await app.request({
    method: 'GET',
    url: `${apiBase()}/flatrate-wiki/scopes/search?${params.toString()}`,
    errorHandler: () => {},
  });

  return (document.data || []).map(resourceToScope).filter(Boolean);
}

export function relevanceMaxActive() {
  const raw = app.forum.attribute('flatRateWikiRelevanceMaxActive');
  const n = Number(raw);
  return Number.isFinite(n) && n > 0 ? n : 5;
}

export function scopeBrowseUrl(scope) {
  if (!scope || !scope.id) {
    return app.route('index');
  }

  const label = scope.label || 'scope';
  const slug = String(label)
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '') || 'scope';

  return app.route('flatrate-wiki.browse.slug', {
    id: scope.id,
    slug,
  });
}

export function formatBreadcrumbTrail(scopes) {
  return (scopes || [])
    .map((scope) => scope.label)
    .filter(Boolean)
    .join(' › ');
}
