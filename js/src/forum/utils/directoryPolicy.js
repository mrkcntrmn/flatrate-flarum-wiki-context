export const DESKTOP_INITIAL = 8;
export const DESKTOP_INLINE_MAX = 24;
export const MOBILE_INITIAL = 6;
export const MOBILE_INLINE_MAX = 18;

export function isMobileWidth(width) {
  return Number(width) < 768;
}

export function directoryCaps(width) {
  return isMobileWidth(width)
    ? { initial: MOBILE_INITIAL, inlineMax: MOBILE_INLINE_MAX }
    : { initial: DESKTOP_INITIAL, inlineMax: DESKTOP_INLINE_MAX };
}

export function slugifyScopeLabel(label) {
  const normalized = String(label || '')
    .normalize('NFKD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');

  return normalized || 'scope';
}

export function visibleInlineChildren(items, expanded, caps) {
  const limit = expanded ? caps.inlineMax : caps.initial;
  return (items || []).slice(0, limit);
}

export function moreCount(totalNormal, caps) {
  return Math.max(0, Math.min(Number(totalNormal) || 0, caps.inlineMax) - caps.initial);
}

export function viewAllCount(totalNormal, caps) {
  return Math.max(0, (Number(totalNormal) || 0) - caps.inlineMax);
}
