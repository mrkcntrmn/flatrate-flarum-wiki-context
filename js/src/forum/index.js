import app from 'flarum/forum/app';

/**
 * FlatRate Wiki Context forum shell.
 *
 * Incomplete WIKI UI must NOT be visible to ordinary users.
 * Public rollout and browse routes remain fail-closed server-side.
 * Ghost-preview shares the same components under admin-only mode (WIKI-001P).
 */
app.initializers.add('flatrate-wiki-context', () => {
  // Skeleton: register future browse route, directory, breadcrumb, preview banner,
  // audience selector, and context display only when server gates allow.
  // No member-visible incomplete UI in R1.
  if (typeof console !== 'undefined' && console.debug) {
    console.debug('[flatrate-wiki-context] initializer registered (gates fail-closed)');
  }
});
