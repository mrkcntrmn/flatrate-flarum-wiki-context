import app from 'flarum/forum/app';
import BrowsePage from './components/BrowsePage';

/**
 * WIKI-001F public browse routes.
 *
 * Server-side middleware remains authoritative. Registering the client route
 * does not open access while browse/public rollout gates are closed.
 */
app.initializers.add('flatrate-wiki-context', () => {
  app.routes['flatrate-wiki.browse'] = {
    path: '/browse/:id',
    component: BrowsePage,
  };

  app.routes['flatrate-wiki.browse.slug'] = {
    path: '/browse/:id/:slug',
    component: BrowsePage,
  };
});
